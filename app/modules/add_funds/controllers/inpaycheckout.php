<?php
defined('BASEPATH') or exit('No direct script access allowed');

class inpaycheckout extends MX_Controller
{
    public $tb_users;
    public $tb_transaction_logs;
    public $tb_payments;
    public $tb_payments_bonuses;
    public $payment_type;
    public $payment_id;
    public $currency_code;
    public $public_key;
    public $secret_key;
    public $payment_fee;
    public $currency_rate;
    public $payment_lib;

    public function __construct($payment = '')
    {
        parent::__construct();
        $this->load->model('add_funds_model', 'model');

        $this->tb_users = USERS;
        $this->tb_transaction_logs = TRANSACTION_LOGS;
        $this->tb_payments = PAYMENTS_METHOD;
        $this->tb_payments_bonuses = PAYMENTS_BONUSES;
        $this->payment_type = 'inpaycheckout';
        $this->currency_code = strtoupper(get_option('currency_code', 'USD'));
        if ($this->currency_code === '') {
            $this->currency_code = 'USD';
        }

        if (!$payment) {
            $payment = $this->model->get('id, type, name, params', $this->tb_payments, ['type' => $this->payment_type]);
        }

        if ($payment) {
            $this->payment_id = $payment->id;
            $params = $payment->params;
            $option = get_value($params, 'option');
            $this->public_key = get_value($option, 'public_key');
            $this->secret_key = get_value($option, 'secret_key');
            $this->payment_fee = (float) get_value($option, 'tnx_fee');
            $this->currency_rate = (float) get_value($option, 'currency_rate', 1);
        } else {
            $this->payment_id = 0;
            $this->public_key = '';
            $this->secret_key = '';
            $this->payment_fee = 0;
            $this->currency_rate = 1;
        }

        if ($this->currency_rate <= 0) {
            $this->currency_rate = 1;
        }

        $this->load->library('inpaycheckoutapi');
        $this->payment_lib = new inpaycheckoutapi($this->secret_key);
    }

    public function index()
    {
        redirect(cn('add_funds'));
    }

    public function create_payment($data_payment = '')
    {
        _is_ajax($data_payment['module']);

        $amount = $data_payment['amount'];
        if (!$amount || $amount <= 0) {
            _validation('error', lang('There_was_an_error_processing_your_request_Please_try_again_later'));
        }

        if (!$this->public_key || !$this->secret_key) {
            _validation('error', lang('this_payment_is_not_active_please_choose_another_payment_or_contact_us_for_more_detail'));
        }

        $user = session('user_current_info');
        if (!$user) {
            _validation('error', lang('There_was_an_error_processing_your_request_Please_try_again_later'));
        }

        $unique = ids();
        $reference = 'inpay_' . strtotime(NOW) . '_' . substr(md5($unique), 0, 8);
        $amount_ngn = $amount;
        if ($this->currency_code !== 'NGN') {
            if ($this->currency_rate <= 0 || $this->currency_rate == 1) {
                _validation('error', 'Set the currency rate in iNPAY Checkout settings before using this gateway.');
            }
            $amount_ngn = round($amount * $this->currency_rate, 2);
        }

        if ($amount_ngn <= 0) {
            _validation('error', lang('amount_must_be_greater_than_zero'));
        }

        $amount_kobo = (int) round($amount_ngn * 100);
        if ($amount_kobo <= 0) {
            _validation('error', lang('amount_must_be_greater_than_zero'));
        }

        $txn_fee = 0;
        if ($this->payment_fee > 0) {
            $txn_fee = round($amount * ($this->payment_fee / 100), 4);
        }

        $data_tnx = [
            'ids' => $unique,
            'uid' => session('uid'),
            'type' => $this->payment_type,
            'transaction_id' => $reference,
            'amount' => $amount,
            'status' => 0,
            'txn_fee' => $txn_fee,
            'created' => NOW,
        ];
        $this->db->insert($this->tb_transaction_logs, $data_tnx);
        $transaction_id = $this->db->insert_id();

        $metadata = [
            'transaction_log_id' => $transaction_id,
            'uid' => session('uid'),
            'reference' => $reference,
            'gateway' => 'smartpanel',
            'callback_url' => cn('add_funds/inpaycheckout/verify'),
            'currency_rate' => $this->currency_rate,
            'base_currency' => $this->currency_code,
            'base_amount' => $amount,
            'charged_amount_ngn' => $amount_ngn,
        ];

        $response = [
            'status' => 'success',
            'message' => 'Launching iNPAY Checkout...',
            'checkout' => [
                'public_key' => $this->public_key,
                'amount_kobo' => $amount_kobo,
                'amount_ngn' => $amount_ngn,
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'reference' => $reference,
                'metadata' => $metadata,
                'verify_url' => cn('add_funds/inpaycheckout/verify'),
                'transaction_id' => $transaction_id,
                'currency_rate' => $this->currency_rate,
                'base_currency' => $this->currency_code,
            ],
        ];

        ms($response);
    }

    public function verify()
    {
        $payload = json_decode($this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = $this->input->post();
        }

        $reference = trim($payload['reference'] ?? '');
        $transaction_id = (int) ($payload['transaction_id'] ?? 0);

        if ($reference === '' || $transaction_id <= 0) {
            _validation('error', 'Invalid payload received.');
        }

        $transaction = $this->model->get('*', $this->tb_transaction_logs, [
            'id' => $transaction_id,
            'transaction_id' => $reference,
            'type' => $this->payment_type,
            'uid' => session('uid'),
        ], '', '', true);

        if (!$transaction) {
            _validation('error', 'Transaction not found.');
        }

        if (is_array($transaction)) {
            $transaction = (object) $transaction;
        }

        if ($transaction->status == 1) {
            ms(['status' => 'success', 'redirect_url' => cn('add_funds/success')]);
        }

        $result = $this->payment_lib->verify_transaction($reference);
        if (!$result || empty($result['success']) || empty($result['data'])) {
            _validation('error', 'Unable to verify transaction.');
        }

        $data = $result['data'];
        $status = strtolower($data['status'] ?? '');
        $verified = !empty($data['verified']);

        if ($status !== 'completed' || !$verified) {
            _validation('error', 'Transaction not completed.'); 
        }

        $this->complete_transaction($transaction);

        ms(['status' => 'success', 'redirect_url' => cn('add_funds/success')]);
    }

    public function webhook()
    {
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
        $timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? 0;
        $event = $_SERVER['HTTP_X_WEBHOOK_EVENT'] ?? '';

        if (!$this->secret_key) {
            $this->response('Missing credentials', 400);
            return;
        }

        $now = round(microtime(true) * 1000);
        if (abs($now - (int) $timestamp) > 5 * 60 * 1000) {
            $this->response('Invalid timestamp', 400);
            return;
        }

        $expected = hash_hmac('sha256', $payload, $this->secret_key);
        $clean = preg_replace('/^sha256=/', '', $signature);
        if (!hash_equals($expected, $clean)) {
            $this->response('Invalid signature', 401);
            return;
        }

        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['data'])) {
            $this->response('Invalid payload', 400);
            return;
        }

        if (!in_array($event, [
            'payment.checkout_payid.completed',
            'payment.virtual_payid.completed',
            'payment.virtual_account.completed',
            'payment.checkout_virtual_account.completed',
        ], true)) {
            $this->response('Ignored event', 200);
            return;
        }

        $transaction = $data['data'];
        $reference = $transaction['reference'] ?? '';
        if ($reference === '') {
            $this->response('Missing reference', 200);
            return;
        }

        $tx = $this->model->get('*', $this->tb_transaction_logs, [
            'transaction_id' => $reference,
            'type' => $this->payment_type,
        ], '', '', true);

        if ($tx) {
            if (is_array($tx)) {
                $tx = (object) $tx;
            }
            if ($tx->status != 1) {
                $verify = $this->payment_lib->verify_transaction($reference);
                if ($verify && !empty($verify['success']) && !empty($verify['data'])) {
                    $verified_data = $verify['data'];
                    $status = strtolower($verified_data['status'] ?? '');
                    $verified_flag = !empty($verified_data['verified']);
                    if ($status === 'completed' && $verified_flag) {
                        $this->complete_transaction($tx);
                    }
                }
            }
        }

        $this->response('OK', 200);
        return;
    }

    protected function complete_transaction($transaction)
    {
        if (!$transaction) {
            return true;
        }

        if (is_array($transaction)) {
            $transaction = (object) $transaction;
        }

        if ($transaction->status == 1) {
            return true;
        }

        $txn_fee = (float) $transaction->txn_fee;
        if ($txn_fee == 0 && $this->payment_fee > 0) {
            $txn_fee = round($transaction->amount * ($this->payment_fee / 100), 4);
        }

        $this->db->update($this->tb_transaction_logs, [
            'txn_fee' => $txn_fee,
            'status' => 1,
        ], ['id' => $transaction->id]);

        $transaction->txn_fee = $txn_fee;
        $transaction->status = 1;

        $this->model->add_funds_bonus_email($transaction, $this->payment_id);

        if (!session('uid')) {
            show_html_add_funds_success_page($transaction->id, $transaction->uid);
        }

        return true;
    }

    protected function response($message, $code = 200)
    {
        $this->output
            ->set_status_header($code)
            ->set_content_type('text/plain')
            ->set_output($message);
    }
}
