<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
/* ── Layout ────────────────────────────────────────────────────── */
#ai-wrap {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 120px);
    min-height: 400px;
    background: #f7f8fa;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
}

/* ── Header ────────────────────────────────────────────────────── */
#ai-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: #fff;
    border-bottom: 1px solid #e8e8e8;
    flex-shrink: 0;
}
#ai-header .ai-title { font-size: 15px; font-weight: 600; color: #333; display: flex; align-items: center; gap: 8px; }
#ai-header .ai-title .ai-dot { width: 8px; height: 8px; border-radius: 50%; background: #5cb85c; display: inline-block; }
#ai-header .ai-title .ai-dot.offline { background: #ccc; }
#ai-header .hdr-actions { display: flex; gap: 6px; }
#ai-header .hdr-actions button { background: none; border: 1px solid #ddd; border-radius: 6px; padding: 5px 10px; font-size: 12px; color: #666; cursor: pointer; transition: background .15s; }
#ai-header .hdr-actions button:hover { background: #f5f5f5; }

/* ── Context bar ───────────────────────────────────────────────── */
#ctx-bar {
    background: #fffdf0;
    border-bottom: 1px solid #f0e8b0;
    padding: 10px 16px;
    display: none;
    flex-shrink: 0;
}
#ctx-bar label { font-size: 11px; font-weight: 600; text-transform: uppercase; color: #888; margin-bottom: 4px; display: block; }
#ctx-input { width: 100%; border: 1px solid #e0d090; border-radius: 6px; padding: 7px 10px; font-size: 13px; resize: none; background: #fff; color: #333; }

/* ── Messages area ─────────────────────────────────────────────── */
#ai-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    -webkit-overflow-scrolling: touch;
}

/* ── Individual message ────────────────────────────────────────── */
.msg-row { display: flex; gap: 8px; max-width: 100%; }
.msg-row.user  { justify-content: flex-end; }
.msg-row.model { justify-content: flex-start; }

.msg-avatar {
    width: 30px; height: 30px;
    border-radius: 50%;
    flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; color: #fff;
    margin-top: 2px;
}
.msg-avatar.ai  { background: linear-gradient(135deg, #4285f4, #0f9d58); }
.msg-avatar.usr { background: #337ab7; }

.msg-bubble {
    max-width: 78%;
    padding: 10px 14px;
    border-radius: 14px;
    font-size: 13.5px;
    line-height: 1.6;
    word-break: break-word;
}
.msg-row.user  .msg-bubble { background: #337ab7; color: #fff; border-bottom-right-radius: 4px; }
.msg-row.model .msg-bubble { background: #fff; color: #333; border: 1px solid #e8e8e8; border-bottom-left-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }

/* ── Markdown inside bubbles ───────────────────────────────────── */
.msg-bubble strong, .msg-bubble b { font-weight: 700; }
.msg-bubble em { font-style: italic; }
.msg-bubble ul { margin: 6px 0 6px 16px; padding: 0; }
.msg-bubble li { margin-bottom: 3px; }
.msg-bubble h3 { font-size: 14px; font-weight: 700; margin: 8px 0 4px; }
.msg-bubble h4 { font-size: 13px; font-weight: 700; margin: 6px 0 3px; }
.msg-bubble code { background: rgba(0,0,0,.07); border-radius: 3px; padding: 1px 5px; font-family: monospace; font-size: 12px; }
.msg-row.user .msg-bubble code { background: rgba(255,255,255,.2); }
.msg-bubble hr { border: none; border-top: 1px solid #eee; margin: 8px 0; }
.msg-bubble p { margin: 0 0 6px; }
.msg-bubble p:last-child { margin-bottom: 0; }

/* ── Tool call chips ───────────────────────────────────────────── */
.tool-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 6px; }
.tool-chip {
    font-size: 10px; padding: 2px 7px;
    background: #e8f4fd; color: #1a73e8;
    border-radius: 10px; border: 1px solid #c8e0f8;
    font-family: monospace;
}

/* ── Typing indicator ──────────────────────────────────────────── */
.typing-bubble { background: #fff; border: 1px solid #e8e8e8; border-radius: 14px; border-bottom-left-radius: 4px; padding: 12px 16px; display: inline-flex; gap: 5px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.typing-dot { width: 7px; height: 7px; border-radius: 50%; background: #bbb; animation: tdot 1.2s ease-in-out infinite; }
.typing-dot:nth-child(2) { animation-delay: .2s; }
.typing-dot:nth-child(3) { animation-delay: .4s; }
@keyframes tdot { 0%,80%,100%{transform:scale(.7);opacity:.5} 40%{transform:scale(1);opacity:1} }

/* ── Input area ────────────────────────────────────────────────── */
#ai-input-area {
    background: #fff;
    border-top: 1px solid #e8e8e8;
    padding: 12px 14px;
    flex-shrink: 0;
}
.input-row { display: flex; gap: 8px; align-items: flex-end; }
#ai-input {
    flex: 1;
    border: 1px solid #ddd;
    border-radius: 20px;
    padding: 9px 16px;
    font-size: 14px;
    resize: none;
    outline: none;
    max-height: 120px;
    overflow-y: auto;
    line-height: 1.5;
    transition: border-color .15s;
    -webkit-overflow-scrolling: touch;
}
#ai-input:focus { border-color: #4285f4; }
#ai-send {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: #337ab7;
    border: none;
    color: #fff;
    cursor: pointer;
    flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s;
}
#ai-send:hover:not(:disabled) { background: #286090; }
#ai-send:disabled { background: #ccc; cursor: default; }
#ai-send svg { width: 18px; height: 18px; }
.input-hint { font-size: 11px; color: #aaa; margin-top: 5px; text-align: center; }

/* ── Settings modal ────────────────────────────────────────────── */
#settings-modal .modal-body label { font-weight: 600; font-size: 13px; }
#settings-modal .modal-body .help-block { font-size: 12px; }

/* ── Welcome screen ────────────────────────────────────────────── */
#ai-welcome {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px;
    text-align: center;
    color: #666;
}
#ai-welcome .welcome-icon { font-size: 40px; margin-bottom: 12px; }
#ai-welcome h4 { font-weight: 700; color: #333; margin-bottom: 8px; }
#ai-welcome p { font-size: 13px; max-width: 340px; margin: 0 auto 16px; }
.suggestion-chips { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
.suggestion-chip {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 16px;
    padding: 6px 14px;
    font-size: 12px;
    cursor: pointer;
    color: #333;
    transition: border-color .15s, background .15s;
}
.suggestion-chip:hover { border-color: #337ab7; background: #f0f6ff; color: #337ab7; }

/* ── Mobile adjustments ────────────────────────────────────────── */
@media (max-width: 767px) {
    #ai-wrap { height: calc(100vh - 90px); border-radius: 0; border-left: none; border-right: none; }
    .msg-bubble { max-width: 88%; }
    #ai-messages { padding: 10px; }
    #ai-header { padding: 10px 12px; }
    .content { padding: 0 !important; }
}
</style>

<div id="wrapper">
<div class="content" style="padding-bottom:0;">

<div id="ai-wrap">

    <!-- Header -->
    <div id="ai-header">
        <div class="ai-title">
            <span class="ai-dot <?php echo $gemini_key ? '' : 'offline'; ?>" id="status-dot"></span>
            AI Assistant
            <small class="text-muted" style="font-weight:400;font-size:12px;">Gemini 3.1 Flash Lite</small>
        </div>
        <div class="hdr-actions">
            <button onclick="toggleCtx()" title="Set context / upcoming events">
                <i class="fa fa-calendar"></i> Context
            </button>
            <button onclick="clearChat()" title="Clear conversation">
                <i class="fa fa-trash-o"></i>
            </button>
            <button onclick="$('#settings-modal').modal('show')" title="Settings">
                <i class="fa fa-cog"></i>
            </button>
        </div>
    </div>

    <!-- Context bar (collapsible) -->
    <div id="ctx-bar">
        <label><i class="fa fa-info-circle"></i> Upcoming events or conditions</label>
        <textarea id="ctx-input" rows="2" placeholder="e.g. Ramadan starts March 1, Hari Raya April 1, outlet closed for renovation week 3..."></textarea>
    </div>

    <!-- Messages or welcome -->
    <div id="ai-messages" style="display:none;"></div>

    <div id="ai-welcome">
        <div class="welcome-icon">&#10024;</div>
        <h4>Your Business AI Assistant</h4>
        <p>Ask about sales trends, member retention, promotion performance, or get a forecast. I'll pull the live data and reason through it.</p>
        <div class="suggestion-chips">
            <span class="suggestion-chip" onclick="useSuggestion(this)">How were sales this week?</span>
            <span class="suggestion-chip" onclick="useSuggestion(this)">Which products sold best last month?</span>
            <span class="suggestion-chip" onclick="useSuggestion(this)">How is member retention looking?</span>
            <span class="suggestion-chip" onclick="useSuggestion(this)">Forecast sales for the next 4 weeks</span>
            <span class="suggestion-chip" onclick="useSuggestion(this)">Which blast campaigns converted best?</span>
            <span class="suggestion-chip" onclick="useSuggestion(this)">What should I do to boost sales?</span>
        </div>
    </div>

    <!-- Input area -->
    <div id="ai-input-area">
        <div class="input-row">
            <textarea id="ai-input" rows="1" placeholder="Ask anything about your sales, members, or promotions&hellip;"></textarea>
            <button id="ai-send" onclick="sendMessage()" disabled>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
        </div>
        <div class="input-hint">Enter to send &nbsp;&middot;&nbsp; Shift+Enter for new line</div>
    </div>

</div><!-- #ai-wrap -->

</div><!-- .content -->
</div><!-- #wrapper -->

<!-- Settings modal -->
<div class="modal fade" id="settings-modal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-cog"></i> AI Settings</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Gemini API Key</label>
                    <input type="password" id="s-api-key" class="form-control" placeholder="AIza&hellip;"
                           value="<?php echo htmlspecialchars($gemini_key); ?>">
                    <p class="help-block">Get a free key at <strong>aistudio.google.com</strong> &rarr; Get API key. Enable billing to avoid quota limits.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveSettings()">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
var ADMIN_URL   = '<?php echo admin_url(); ?>';
var HAS_KEY     = <?php echo $gemini_key ? 'true' : 'false'; ?>;
var _history    = [];
var _busy       = false;
var _ctxOpen    = false;

window.addEventListener('load', function() {
    var inp = document.getElementById('ai-input');
    inp.addEventListener('input',   autoResize);
    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
    inp.addEventListener('input', function() {
        document.getElementById('ai-send').disabled = !inp.value.trim() || _busy;
    });
    if (!HAS_KEY) {
        setTimeout(function(){ $('#settings-modal').modal('show'); }, 400);
    }
});

function autoResize() {
    var el = document.getElementById('ai-input');
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function toggleCtx() {
    _ctxOpen = !_ctxOpen;
    document.getElementById('ctx-bar').style.display = _ctxOpen ? 'block' : 'none';
}

function useSuggestion(el) {
    document.getElementById('ai-input').value = el.textContent;
    autoResize();
    document.getElementById('ai-send').disabled = false;
    sendMessage();
}

function sendMessage() {
    var inp = document.getElementById('ai-input');
    var msg = inp.value.trim();
    if (!msg || _busy) return;

    if (!HAS_KEY) {
        $('#settings-modal').modal('show');
        return;
    }

    document.getElementById('ai-welcome').style.display  = 'none';
    document.getElementById('ai-messages').style.display = 'flex';

    inp.value = '';
    inp.style.height = 'auto';
    document.getElementById('ai-send').disabled = true;
    _busy = true;

    appendMessage('user', msg);
    _history.push({ role: 'user', text: msg });

    var typing = appendTyping();
    scrollBottom();

    var ctx = document.getElementById('ctx-input').value.trim();

    $.post(ADMIN_URL + 'pos/ajax_ai_chat', {
        message: msg,
        history: JSON.stringify(_history.slice(0, -1)),
        context: ctx
    })
    .done(function(resp) {
        if (typeof resp === 'string') { try { resp = JSON.parse(resp); } catch(e){} }
        removeTyping(typing);

        if (!resp || !resp.success) {
            appendError(resp && resp.error ? resp.error : 'Something went wrong.');
        } else {
            appendMessage('model', resp.reply, resp.tool_calls || []);
            _history.push({ role: 'model', text: resp.reply });
        }
    })
    .fail(function() {
        removeTyping(typing);
        appendError('Request failed. Check your connection.');
    })
    .always(function() {
        _busy = false;
        document.getElementById('ai-send').disabled = false;
        document.getElementById('ai-input').focus();
        scrollBottom();
    });
}

function appendMessage(role, text, toolCalls) {
    var container = document.getElementById('ai-messages');
    var row = document.createElement('div');
    row.className = 'msg-row ' + role;

    var avatar = '<div class="msg-avatar ' + (role === 'model' ? 'ai' : 'usr') + '">'
               + (role === 'model' ? '&#10022;' : '<i class="fa fa-user" style="font-size:11px;"></i>') + '</div>';

    var chips = '';
    if (toolCalls && toolCalls.length) {
        chips = '<div class="tool-chips">'
              + toolCalls.map(function(t){ return '<span class="tool-chip">&#9889; ' + t + '</span>'; }).join('')
              + '</div>';
    }

    var bubble = '<div class="msg-bubble">' + chips + renderMarkdown(text) + '</div>';

    row.innerHTML = role === 'user'
        ? bubble + avatar
        : avatar + bubble;

    container.appendChild(row);
    scrollBottom();
    return row;
}

function appendTyping() {
    var container = document.getElementById('ai-messages');
    var row = document.createElement('div');
    row.className = 'msg-row model';
    row.id = 'typing-row';
    row.innerHTML = '<div class="msg-avatar ai">&#10022;</div>'
                  + '<div class="typing-bubble"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>';
    container.appendChild(row);
    scrollBottom();
    return row;
}

function removeTyping(row) {
    if (row && row.parentNode) row.parentNode.removeChild(row);
}

function appendError(msg) {
    var container = document.getElementById('ai-messages');
    var row = document.createElement('div');
    row.className = 'msg-row model';
    row.innerHTML = '<div class="msg-avatar ai" style="background:#d9534f;">!</div>'
                  + '<div class="msg-bubble" style="background:#fdf2f2;border-color:#f5c6cb;color:#721c24;">' + htmlEnc(msg) + '</div>';
    container.appendChild(row);
}

function scrollBottom() {
    var el = document.getElementById('ai-messages');
    el.scrollTop = el.scrollHeight;
}

function clearChat() {
    _history = [];
    document.getElementById('ai-messages').innerHTML = '';
    document.getElementById('ai-messages').style.display = 'none';
    document.getElementById('ai-welcome').style.display  = 'flex';
}

function renderMarkdown(text) {
    if (!text) return '';
    var s = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    s = s.replace(/```[\s\S]*?```/g, function(m) {
        return '<pre style="background:rgba(0,0,0,.06);border-radius:6px;padding:8px 10px;font-size:12px;overflow-x:auto;margin:6px 0;">'
             + m.slice(3, -3).replace(/^\w+\n/, '') + '</pre>';
    });
    s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    s = s.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    s = s.replace(/^### (.+)$/gm, '<h4>$1</h4>');
    s = s.replace(/^## (.+)$/gm,  '<h3>$1</h3>');
    s = s.replace(/^---+$/gm, '<hr>');
    s = s.replace(/((?:^[*\-] .+\n?)+)/gm, function(block) {
        var items = block.trim().split(/\n/).map(function(l) {
            return '<li>' + l.replace(/^[*\-] /, '') + '</li>';
        }).join('');
        return '<ul>' + items + '</ul>';
    });
    s = s.replace(/((?:^\d+\. .+\n?)+)/gm, function(block) {
        var items = block.trim().split(/\n/).map(function(l) {
            return '<li>' + l.replace(/^\d+\. /, '') + '</li>';
        }).join('');
        return '<ol>' + items + '</ol>';
    });
    s = s.replace(/\n{2,}/g, '</p><p>');
    s = s.replace(/\n/g, '<br>');
    s = '<p>' + s + '</p>';
    s = s.replace(/<p>(<(?:ul|ol|h[234]|hr|pre)[^>]*>)/g, '$1');
    s = s.replace(/(<\/(?:ul|ol|h[234]|pre)>)<\/p>/g, '$1');
    return s;
}

function htmlEnc(s) { return $('<span>').text(s).html(); }

function saveSettings() {
    var key = $('#s-api-key').val().trim();
    $.post(ADMIN_URL + 'pos/ajax_save_ai_settings', { gemini_api_key: key })
    .done(function(resp) {
        if (typeof resp === 'string') { try { resp = JSON.parse(resp); } catch(e){} }
        if (resp && resp.success) {
            HAS_KEY = !!key;
            document.getElementById('status-dot').className = 'ai-dot' + (HAS_KEY ? '' : ' offline');
            $('#settings-modal').modal('hide');
        }
    });
}
</script>
<?php init_tail(); ?>
