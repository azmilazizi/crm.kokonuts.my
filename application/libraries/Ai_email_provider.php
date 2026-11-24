<?php

defined('BASEPATH') or exit('No direct script access allowed');

interface Ai_email_provider
{
    public function test($mailbox);
    public function search($mailbox, $criteria, $page, $perPage);
    public function get($mailbox, $folder, $uid, $includeBody = true);
    public function attachment($mailbox, $folder, $uid, $part);
    public function triage($mailbox, $folder, $uid, $action, $targetFolder = '');
}

class Ai_imap_provider implements Ai_email_provider
{
    private function open($mailbox, $folder = 'INBOX', $readonly = true)
    {
        if (!function_exists('imap_open')) throw new RuntimeException('The PHP IMAP extension is not enabled on the server.');
        $flags = $mailbox->imap_encryption === 'none' ? '/imap/novalidate-cert' : '/imap/' . $mailbox->imap_encryption . '/validate-cert';
        $resource = @imap_open('{' . $mailbox->imap_host . ':' . (int) $mailbox->imap_port . $flags . '}' . ($folder ?: 'INBOX'), $mailbox->imap_username, get_instance()->encryption->decrypt($mailbox->imap_password), $readonly ? OP_READONLY : 0, 1);
        if (!$resource) throw new RuntimeException('IMAP connection failed: ' . (imap_last_error() ?: 'check the host, port, encryption and credentials.'));
        return $resource;
    }

    public function test($mailbox)
    {
        $imap = $this->open($mailbox);
        @imap_close($imap);
        return true;
    }

    public function search($mailbox, $criteria, $page, $perPage)
    {
        $folder = $criteria['folder'] ?: 'INBOX';
        $imap = $this->open($mailbox, $folder);
        $query = 'ALL';
        if (!empty($criteria['sender'])) $query .= ' FROM "' . addslashes($criteria['sender']) . '"';
        if (!empty($criteria['subject'])) $query .= ' SUBJECT "' . addslashes($criteria['subject']) . '"';
        if (!empty($criteria['text'])) $query .= ' TEXT "' . addslashes($criteria['text']) . '"';
        if (!empty($criteria['date_from'])) $query .= ' SINCE ' . date('d-M-Y', strtotime($criteria['date_from']));
        if (!empty($criteria['date_to'])) $query .= ' BEFORE ' . date('d-M-Y', strtotime($criteria['date_to'] . ' +1 day'));
        $uids = @imap_search($imap, $query, SE_UID) ?: [];
        rsort($uids, SORT_NUMERIC);
        $offset = max(0, ($page - 1) * $perPage);
        $slice = array_slice($uids, $offset, $perPage);
        $items = [];
        foreach ($slice as $uid) {
            $overview = imap_fetch_overview($imap, (string) $uid, FT_UID);
            if (empty($overview[0])) continue;
            $o = $overview[0];
            if (!empty($criteria['allowed_senders']) && !$this->sender_matches($o->from ?? '', $criteria['allowed_senders'])) continue;
            $structure = @imap_fetchstructure($imap, $uid, FT_UID);
            $hasAttachment = $this->has_attachment($structure);
            if (isset($criteria['has_attachment']) && $criteria['has_attachment'] !== null && (bool) $criteria['has_attachment'] !== $hasAttachment) continue;
            $items[] = ['uid' => (int) $uid, 'folder' => $folder, 'subject' => $o->subject ?? '', 'from' => $o->from ?? '', 'date' => $o->date ?? '', 'seen' => !empty($o->seen), 'flagged' => !empty($o->flagged), 'has_attachment' => $hasAttachment];
        }
        @imap_close($imap);
        return ['page' => $page, 'per_page' => $perPage, 'total' => count($uids), 'messages' => $items];
    }

    private function sender_matches($sender, $allowed)
    {
        $sender = strtolower((string) $sender);
        if (preg_match('/<([^>]+)>/', $sender, $match)) $sender = trim($match[1]);
        foreach ($allowed as $entry) {
            $entry = strtolower(trim($entry));
            if ($entry !== '' && ($sender === $entry || (strpos($entry, '@') === 0 && substr($sender, -strlen($entry)) === $entry) || (strpos($entry, '*@') === 0 && substr($sender, strpos($entry, '@')) === substr($entry, strpos($entry, '@'))))) return true;
        }
        return false;
    }

    public function get($mailbox, $folder, $uid, $includeBody = true)
    {
        $imap = $this->open($mailbox, $folder);
        $overview = imap_fetch_overview($imap, (string) $uid, FT_UID);
        if (empty($overview[0])) throw new RuntimeException('Message not found.');
        $o = $overview[0];
        $body = $includeBody ? imap_fetchbody($imap, $uid, 1, FT_UID | FT_PEEK) : '';
        $structure = @imap_fetchstructure($imap, $uid, FT_UID);
        $result = ['uid' => (int) $uid, 'folder' => $folder, 'subject' => $o->subject ?? '', 'from' => $o->from ?? '', 'to' => $o->to ?? '', 'date' => $o->date ?? '', 'message_id' => $o->message_id ?? '', 'seen' => !empty($o->seen), 'flagged' => !empty($o->flagged), 'body' => mb_substr(trim((string) $body), 0, 20000), 'attachments' => $this->attachments($structure)];
        @imap_close($imap);
        return $result;
    }

    private function has_attachment($part)
    {
        return !empty($this->attachments($part));
    }

    private function attachments($part, $prefix = '')
    {
        $found = [];
        if (!$part) return $found;
        foreach (($part->dparameters ?? []) as $parameter) {
            if (strtolower($parameter->attribute ?? '') === 'filename') $found[] = ['part' => $prefix ?: '1', 'filename' => $parameter->value, 'type' => 'attachment'];
        }
        foreach (($part->parameters ?? []) as $parameter) {
            if (strtolower($parameter->attribute ?? '') === 'name') $found[] = ['part' => $prefix ?: '1', 'filename' => $parameter->value, 'type' => 'attachment'];
        }
        foreach (($part->parts ?? []) as $index => $child) {
            $number = $prefix ? $prefix . '.' . ($index + 1) : (string) ($index + 1);
            $found = array_merge($found, $this->attachments($child, $number));
        }
        return $found;
    }

    public function attachment($mailbox, $folder, $uid, $part)
    {
        $imap = $this->open($mailbox, $folder);
        $data = imap_fetchbody($imap, $uid, (string) $part, FT_UID | FT_PEEK);
        @imap_close($imap);
        $data = base64_decode($data, true) ?: quoted_printable_decode($data);
        if (strlen($data) > 10 * 1024 * 1024) throw new RuntimeException('Attachment exceeds the 10 MB limit.');
        return ['content_base64' => base64_encode($data), 'size' => strlen($data)];
    }

    public function triage($mailbox, $folder, $uid, $action, $targetFolder = '')
    {
        $imap = $this->open($mailbox, $folder, false);
        $ok = false;
        if ($action === 'mark_read') $ok = imap_setflag_full($imap, (string) $uid, '\\Seen', ST_UID);
        if ($action === 'mark_unread') $ok = imap_clearflag_full($imap, (string) $uid, '\\Seen', ST_UID);
        if ($action === 'flag') $ok = imap_setflag_full($imap, (string) $uid, '\\Flagged', ST_UID);
        if ($action === 'unflag') $ok = imap_clearflag_full($imap, (string) $uid, '\\Flagged', ST_UID);
        if ($action === 'move') $ok = imap_mail_move($imap, (string) $uid, $targetFolder, CP_UID) && imap_expunge($imap);
        @imap_close($imap);
        if (!$ok) throw new RuntimeException('IMAP could not apply the requested action.');
        return ['success' => true, 'action' => $action];
    }
}
