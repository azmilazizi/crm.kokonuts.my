<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ai_email extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (staff_cant('view', 'settings')) access_denied('settings');
        $this->load->model('ai_email_model');
        $this->load->library('ai_email_service');
    }

    public function index($id = '')
    {
        $data['title'] = 'AI Email Access';
        $data['mailboxes'] = $this->ai_email_model->get();
        if (!is_admin()) {
            $data['mailboxes'] = array_values(array_filter($data['mailboxes'], function ($item) { return (int) $item->owner_staff_id === (int) get_staff_user_id(); }));
        }
        $data['mailbox'] = $id ? $this->ai_email_model->get($id) : null;
        if ($data['mailbox'] && !is_admin() && (int) $data['mailbox']->owner_staff_id !== (int) get_staff_user_id()) access_denied('settings');
        $data['staff'] = $this->staff_model->get('', ['active' => 1]);
        $data['logs'] = $this->ai_email_model->audit_logs();
        $this->load->view('admin/ai_email/manage', $data);
    }

    public function save($id = '')
    {
        if (!is_admin() || staff_cant('edit', 'settings')) access_denied('settings');
        $post = $this->input->post();
        if (empty($post['email']) || !filter_var($post['email'], FILTER_VALIDATE_EMAIL) || empty($post['display_name']) || empty($post['imap_host']) || empty($post['imap_username']) || (!$id && empty($post['imap_password']))) {
            set_alert('danger', 'Email, display name, IMAP host, username and a password for new mailboxes are required.');
            redirect(admin_url('ai_email' . ($id ? '/index/' . $id : '')));
        }
        $this->ai_email_model->save($post, $id);
        set_alert('success', 'AI mailbox saved.');
        redirect(admin_url('ai_email'));
    }

    public function test($id)
    {
        if (!is_admin() || staff_cant('edit', 'settings')) access_denied('settings');
        try {
            echo json_encode($this->ai_email_service->test($id));
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
