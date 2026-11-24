<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ai_email_model extends App_Model
{
    public function get($id = '')
    {
        if ($id === '') {
            return $this->db->order_by('display_name', 'asc')->get(db_prefix() . 'ai_email_mailboxes')->result();
        }

        return $this->db->where('id', (int) $id)->get(db_prefix() . 'ai_email_mailboxes')->row();
    }

    public function save($data, $id = '')
    {
        $now = date('Y-m-d H:i:s');
        $row = [
            'email' => trim($data['email']),
            'display_name' => trim($data['display_name']),
            'provider' => 'imap',
            'imap_host' => trim($data['imap_host']),
            'imap_port' => (int) ($data['imap_port'] ?: 993),
            'imap_encryption' => in_array($data['imap_encryption'], ['ssl', 'tls', 'none'], true) ? $data['imap_encryption'] : 'ssl',
            'imap_username' => trim($data['imap_username']),
            'owner_staff_id' => !empty($data['owner_staff_id']) ? (int) $data['owner_staff_id'] : null,
            'access_level' => in_array($data['access_level'], ['off', 'read', 'triage'], true) ? $data['access_level'] : 'off',
            'enabled' => !empty($data['enabled']) ? 1 : 0,
            'scope_rules' => json_encode([
                'senders' => array_values(array_filter(array_map('trim', preg_split('/[,\r\n]+/', (string) ($data['scope_senders'] ?? ''))))),
                'folders' => array_values(array_filter(array_map('trim', preg_split('/[,\r\n]+/', (string) ($data['scope_folders'] ?? ''))))),
            ]),
            'dateupdated' => $now,
        ];

        if (!empty($data['imap_password'])) {
            $row['imap_password'] = $this->encryption->encrypt($data['imap_password']);
        }

        if ($id === '') {
            if (!isset($row['imap_password'])) {
                return false;
            }
            $row['datecreated'] = $now;
            $this->db->insert(db_prefix() . 'ai_email_mailboxes', $row);
            return $this->db->insert_id();
        }

        $this->db->where('id', (int) $id)->update(db_prefix() . 'ai_email_mailboxes', $row);
        return true;
    }

    public function audit($data)
    {
        $this->db->insert(db_prefix() . 'ai_email_audit_logs', [
            'mailbox_id' => !empty($data['mailbox_id']) ? (int) $data['mailbox_id'] : null,
            'tool' => substr((string) $data['tool'], 0, 64),
            'message_identifier' => isset($data['message_identifier']) ? substr((string) $data['message_identifier'], 0, 255) : null,
            'action' => isset($data['action']) ? substr((string) $data['action'], 0, 64) : null,
            'result' => substr((string) $data['result'], 0, 32),
            'error_code' => isset($data['error_code']) ? substr((string) $data['error_code'], 0, 64) : null,
            'datecreated' => date('Y-m-d H:i:s'),
        ]);
        $days = max(1, (int) get_option('ai_email_audit_retention_days'));
        $this->db->where('datecreated <', date('Y-m-d H:i:s', time() - ($days * 86400)))->delete(db_prefix() . 'ai_email_audit_logs');
    }

    public function audit_logs($filters = [])
    {
        if (!empty($filters['tool'])) $this->db->like('tool', $filters['tool']);
        if (!empty($filters['mailbox_id'])) $this->db->where('mailbox_id', (int) $filters['mailbox_id']);
        return $this->db->order_by('datecreated', 'desc')->limit(200)->get(db_prefix() . 'ai_email_audit_logs')->result();
    }
}
