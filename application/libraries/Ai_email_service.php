<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ai_email_service
{
    const MAX_PAGE = 100;
    const MAX_BODY = 20000;
    const MAX_ATTACHMENT = 10485760;

    private $ci;
    private $provider;

    public function __construct()
    {
        $this->ci = get_instance();
        $this->ci->load->model('ai_email_model');
        require_once APPPATH . 'libraries/Ai_email_provider.php';
        $this->provider = new Ai_imap_provider();
    }

    public function list_accessible()
    {
        $rows = [];
        foreach ($this->ci->ai_email_model->get() as $mailbox) {
            if (!$this->allowed($mailbox, 'read')) continue;
            $rows[] = ['id' => (int) $mailbox->id, 'email' => $mailbox->email, 'display_name' => $mailbox->display_name, 'owner_staff_id' => $mailbox->owner_staff_id, 'access_level' => $mailbox->access_level, 'last_used_at' => $mailbox->last_used_at];
        }
        $this->ci->ai_email_model->audit(['tool' => 'list_accessible_mailboxes', 'action' => 'list', 'result' => 'success']);
        return ['mailboxes' => $rows];
    }

    public function search($args)
    {
        $mailbox = $this->mailbox($args['mailbox'] ?? 0, 'read', 'search_emails');
        $rules = json_decode((string) $mailbox->scope_rules, true) ?: [];
        $criteria = ['folder' => trim((string) ($args['folder'] ?? 'INBOX')), 'sender' => trim((string) ($args['sender'] ?? '')), 'subject' => trim((string) ($args['subject'] ?? '')), 'text' => trim((string) ($args['text'] ?? '')), 'date_from' => $args['date_from'] ?? '', 'date_to' => $args['date_to'] ?? '', 'has_attachment' => array_key_exists('has_attachment', $args) ? (bool) $args['has_attachment'] : null, 'allowed_senders' => array_filter($rules['senders'] ?? [])];
        $this->assert_scope($mailbox, $criteria['folder'], $criteria['sender']);
        $result = $this->provider->search($mailbox, $criteria, max(1, (int) ($args['page'] ?? 1)), min(self::MAX_PAGE, max(1, (int) ($args['per_page'] ?? 25))));
        $this->used($mailbox, 'search_emails', null, 'search', 'success');
        return $result;
    }

    public function get_email($args)
    {
        $mailbox = $this->mailbox($args['mailbox'] ?? 0, 'read', 'get_email');
        $folder = trim((string) ($args['folder'] ?? 'INBOX'));
        $this->assert_scope($mailbox, $folder, '');
        $result = $this->provider->get($mailbox, $folder, (int) ($args['uid'] ?? 0), true);
        $this->assert_scope($mailbox, $folder, $result['from'] ?? '');
        $this->used($mailbox, 'get_email', (string) ($args['uid'] ?? ''), 'read', 'success');
        return $result;
    }

    public function attachment($args)
    {
        $mailbox = $this->mailbox($args['mailbox'] ?? 0, 'read', 'get_email_attachment');
        $folder = trim((string) ($args['folder'] ?? 'INBOX'));
        $this->assert_scope($mailbox, $folder, '');
        $result = $this->provider->attachment($mailbox, $folder, (int) ($args['uid'] ?? 0), (string) ($args['part'] ?? ''));
        $this->used($mailbox, 'get_email_attachment', (string) ($args['uid'] ?? ''), 'download_attachment', 'success');
        return $result;
    }

    public function triage($args)
    {
        $mailbox = $this->mailbox($args['mailbox'] ?? 0, 'triage', 'triage_email');
        $folder = trim((string) ($args['folder'] ?? 'INBOX'));
        $action = trim((string) ($args['action'] ?? ''));
        if (!in_array($action, ['mark_read', 'mark_unread', 'flag', 'unflag', 'move'], true)) throw new RuntimeException('Action is not allowed.');
        $target = trim((string) ($args['target_folder'] ?? ''));
        $this->assert_scope($mailbox, $folder, '');
        if ($action === 'move') {
            if ($target === '') throw new RuntimeException('target_folder is required for move.');
            $this->assert_scope($mailbox, $target, '');
        }
        $result = $this->provider->triage($mailbox, $folder, (int) ($args['uid'] ?? 0), $action, $target);
        $this->used($mailbox, 'triage_email', (string) ($args['uid'] ?? ''), $action, 'success');
        return $result;
    }

    public function test($id)
    {
        $mailbox = $this->ci->ai_email_model->get($id);
        if (!$mailbox) throw new RuntimeException('Mailbox not found.');
        try {
            $this->provider->test($mailbox);
            $this->ci->db->where('id', $id)->update(db_prefix() . 'ai_email_mailboxes', ['last_connection_status' => 'success', 'last_connection_message' => 'Connection successful.', 'dateupdated' => date('Y-m-d H:i:s')]);
            return ['success' => true, 'message' => 'Connection successful.'];
        } catch (Throwable $e) {
            $this->ci->db->where('id', $id)->update(db_prefix() . 'ai_email_mailboxes', ['last_connection_status' => 'failed', 'last_connection_message' => substr($e->getMessage(), 0, 255), 'dateupdated' => date('Y-m-d H:i:s')]);
            throw $e;
        }
    }

    private function mailbox($id, $level, $tool)
    {
        $mailbox = $this->ci->ai_email_model->get((int) $id);
        if (!$mailbox || !$this->allowed($mailbox, $level)) {
            $this->ci->ai_email_model->audit(['mailbox_id' => $mailbox ? $mailbox->id : null, 'tool' => $tool, 'action' => 'access_denied', 'result' => 'denied', 'error_code' => 'mailbox_not_available']);
            throw new RuntimeException('Mailbox is not available for this operation.');
        }
        return $mailbox;
    }

    private function allowed($mailbox, $level)
    {
        if (!$mailbox->enabled || $mailbox->access_level === 'off') return false;
        return $level === 'read' ? in_array($mailbox->access_level, ['read', 'triage'], true) : $mailbox->access_level === 'triage';
    }

    private function assert_scope($mailbox, $folder, $sender)
    {
        $rules = json_decode((string) $mailbox->scope_rules, true) ?: [];
        $folders = array_filter($rules['folders'] ?? []);
        $senders = array_filter($rules['senders'] ?? []);
        if ($folders && !in_array($folder, $folders, true)) throw new RuntimeException('The requested folder is outside the mailbox scope.');
        if ($senders && $sender && !$this->matches_sender($sender, $senders)) throw new RuntimeException('The requested sender is outside the mailbox scope.');
    }

    private function matches_sender($sender, $allowed)
    {
        foreach ($allowed as $entry) {
            if (strcasecmp($sender, $entry) === 0 || (strpos($entry, '@') === 0 && substr(strtolower($sender), -strlen($entry)) === strtolower($entry)) || (strpos($entry, '*@') === 0 && substr(strtolower($sender), strpos($entry, '@')) === strtolower(substr($entry, strpos($entry, '@'))))) return true;
        }
        return false;
    }

    private function used($mailbox, $tool, $identifier, $action, $result)
    {
        $this->ci->ai_email_model->audit(['mailbox_id' => $mailbox->id, 'tool' => $tool, 'message_identifier' => $identifier, 'action' => $action, 'result' => $result]);
        $this->ci->db->where('id', $mailbox->id)->update(db_prefix() . 'ai_email_mailboxes', ['last_used_at' => date('Y-m-d H:i:s')]);
    }
}
