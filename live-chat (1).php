<?php
require_once __DIR__ . '/_common.php';
$csrf = csrf_token();
$pdo = db();
$unreadTotal = 0;
try {
    $unreadTotal = (int)$pdo->query("SELECT COUNT(*) FROM live_chat_messages WHERE sender = 'visitor' AND is_read_by_admin = 0")->fetchColumn();
} catch (Throwable $e) {
    // The setup page will show the database initialization step if tables are absent.
}
ts_admin_page_start('Live Chat', 'live-chat', 'Reply to website visitors directly from your TOP STUDIO Admin Panel.');
?>
<style>
.ts-live-admin{display:grid;grid-template-columns:minmax(260px,330px) minmax(0,1fr);gap:18px;min-height:610px}
.ts-chat-box{background:#fff;border:1px solid #e8e3d8;border-radius:16px;overflow:hidden;min-width:0}
.ts-chat-box-head{padding:17px 18px;border-bottom:1px solid #eee9df;display:flex;align-items:center;justify-content:space-between;gap:12px}
.ts-chat-box-head h2{margin:0;font-size:18px;color:#191816}.ts-chat-box-head small{color:#777}
.ts-chat-list{max-height:650px;overflow:auto}
.ts-chat-conv{width:100%;text-align:left;display:block;padding:15px 17px;border:0;border-bottom:1px solid #f0ede7;background:#fff;cursor:pointer;color:#24211c}
.ts-chat-conv:hover,.ts-chat-conv.is-active{background:#f8f5ef}.ts-chat-conv-top{display:flex;align-items:center;justify-content:space-between;gap:10px}.ts-chat-conv b{font-size:14px}.ts-chat-conv small{display:block;color:#777;margin-top:4px}.ts-chat-preview{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:260px;color:#777;font-size:12px;margin-top:7px}
.ts-chat-unread{background:#b38b4b;color:#fff;border-radius:99px;padding:2px 8px;font-size:11px;font-weight:700}
.ts-chat-thread{display:flex;flex-direction:column;min-height:610px}.ts-chat-person{padding:16px 19px;border-bottom:1px solid #eee9df}.ts-chat-person strong{display:block;color:#191816}.ts-chat-person small{color:#777}.ts-chat-thread-messages{flex:1;min-height:300px;max-height:500px;overflow:auto;padding:20px;background:#fbfaf8;display:flex;flex-direction:column;gap:10px}
.ts-chat-bubble{max-width:min(78%,560px);padding:11px 14px;border-radius:13px;background:#eeeae2;color:#28251f;align-self:flex-start;overflow-wrap:anywhere;white-space:pre-wrap;font-size:14px;line-height:1.5}.ts-chat-bubble.is-admin{background:#26231e;color:#fff;align-self:flex-end}.ts-chat-bubble time{display:block;margin-top:5px;font-size:10px;opacity:.65}
.ts-chat-reply{border-top:1px solid #eee9df;padding:14px;display:flex;gap:10px}.ts-chat-reply textarea{flex:1;min-height:48px;max-height:120px;resize:vertical;border:1px solid #ded8cd;border-radius:10px;padding:12px;font:inherit}.ts-chat-reply button{border:0;border-radius:10px;padding:0 18px;background:#b38b4b;color:#fff;font-weight:700;cursor:pointer}.ts-chat-toolbar{display:flex;gap:8px}.ts-chat-toolbar button{border:1px solid #ded8cd;background:#fff;border-radius:8px;padding:7px 10px;cursor:pointer;color:#35312a}.ts-chat-empty{padding:30px;color:#777;text-align:center;margin:auto}.ts-chat-status{font-size:11px;border-radius:20px;padding:4px 9px;background:#edf6ef;color:#28723c}.ts-chat-status.closed{background:#f4ece7;color:#8c583f}.ts-chat-note{padding:10px 16px;color:#777;font-size:12px;border-top:1px solid #eee9df}
@media(max-width:800px){.ts-live-admin{grid-template-columns:1fr}.ts-chat-list{max-height:300px}.ts-chat-thread{min-height:560px}.ts-chat-thread-messages{min-height:280px}.ts-chat-bubble{max-width:90%}}
</style>
<section class="adm-panel" style="margin-bottom:16px">
  <div class="adm-panel-heading"><div><p class="adm-eyebrow">CLIENT RELATIONS</p><h2>Visitor conversations</h2><p class="adm-help">Unread visitor messages: <b id="tsChatUnreadCount"><?=number_format($unreadTotal)?></b>. This inbox is private to logged-in Admin users.</p></div><div class="ts-chat-toolbar"><a class="adm-button adm-button-outline" href="live-chat-setup.php">Database setup</a><button type="button" class="adm-button adm-button-outline" id="tsChatRefresh">Refresh</button></div></div>
</section>
<div class="ts-live-admin" id="tsLiveAdmin" data-api="live-chat-api.php" data-csrf="<?=e($csrf)?>">
  <section class="ts-chat-box">
    <div class="ts-chat-box-head"><h2>Inbox</h2><small id="tsChatInboxCount">Loading…</small></div>
    <div class="ts-chat-list" id="tsChatConversationList"><div class="ts-chat-empty">Loading conversations…</div></div>
  </section>
  <section class="ts-chat-box ts-chat-thread">
    <div class="ts-chat-box-head"><h2>Conversation</h2><div class="ts-chat-toolbar"><span class="ts-chat-status" id="tsChatStatus" hidden>Open</span><button type="button" id="tsChatToggleStatus" hidden>Close chat</button></div></div>
    <div class="ts-chat-person" id="tsChatPerson" hidden></div>
    <div class="ts-chat-thread-messages" id="tsChatMessages"><div class="ts-chat-empty">Select a visitor conversation to read and reply.</div></div>
    <form class="ts-chat-reply" id="tsChatReplyForm" hidden>
      <textarea id="tsChatReplyText" maxlength="2000" placeholder="Write a reply…" required></textarea>
      <button type="submit">Send reply</button>
    </form>
    <div class="ts-chat-note">Replies are delivered inside the visitor’s chat window while they are on the website or when they return on the same browser.</div>
  </section>
</div>
<script>
(() => {
  const root = document.getElementById('tsLiveAdmin');
  if (!root) return;
  const api = root.dataset.api;
  const csrf = root.dataset.csrf;
  const listEl = document.getElementById('tsChatConversationList');
  const listCount = document.getElementById('tsChatInboxCount');
  const unreadCount = document.getElementById('tsChatUnreadCount');
  const messagesEl = document.getElementById('tsChatMessages');
  const personEl = document.getElementById('tsChatPerson');
  const replyForm = document.getElementById('tsChatReplyForm');
  const replyText = document.getElementById('tsChatReplyText');
  const statusEl = document.getElementById('tsChatStatus');
  const statusButton = document.getElementById('tsChatToggleStatus');
  let conversations = [];
  let selectedId = 0;
  let lastMessageId = 0;
  let loadingMessages = false;

  const escapeText = value => String(value ?? '');
  async function getJson(url, options = {}) {
    const response = await fetch(url, {credentials:'same-origin', cache:'no-store', ...options});
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.ok) throw new Error(data.message || 'Request failed.');
    return data;
  }
  function renderList() {
    listEl.replaceChildren();
    listCount.textContent = conversations.length + ' conversations';
    unreadCount.textContent = conversations.reduce((n, c) => n + Number(c.unread_count || 0), 0);
    if (!conversations.length) {
      const empty = document.createElement('div'); empty.className = 'ts-chat-empty'; empty.textContent = 'No visitor chats yet. New website chats will appear here.'; listEl.append(empty); return;
    }
    conversations.forEach(c => {
      const btn = document.createElement('button'); btn.type='button'; btn.className='ts-chat-conv' + (Number(c.id) === selectedId ? ' is-active' : '');
      const top = document.createElement('div'); top.className='ts-chat-conv-top';
      const name = document.createElement('b'); name.textContent = c.visitor_name;
      top.append(name);
      const unread = Number(c.unread_count || 0);
      if (unread) { const badge = document.createElement('span'); badge.className='ts-chat-unread'; badge.textContent=String(unread); top.append(badge); }
      const phone = document.createElement('small'); phone.textContent = c.visitor_phone || '';
      const preview = document.createElement('div'); preview.className='ts-chat-preview'; preview.textContent = c.last_message || 'No messages';
      btn.append(top, phone, preview);
      btn.addEventListener('click', () => selectConversation(Number(c.id)));
      listEl.append(btn);
    });
  }
  function addMessage(m) {
    const bubble = document.createElement('div');
    bubble.className = 'ts-chat-bubble' + (m.sender === 'admin' ? ' is-admin' : '');
    const body = document.createElement('div'); body.textContent = m.message;
    const time = document.createElement('time'); time.textContent = (m.sender === 'admin' ? 'TOP STUDIO · ' : 'VISITOR · ') + (m.created_at || '');
    bubble.append(body, time); messagesEl.append(bubble);
  }
  async function loadMessages(initial = false) {
    if (!selectedId || loadingMessages) return;
    loadingMessages = true;
    try {
      const after = initial ? 0 : lastMessageId;
      const data = await getJson(api + '?action=messages&conversation_id=' + selectedId + '&after_id=' + after);
      if (initial) { messagesEl.replaceChildren(); lastMessageId = 0; }
      (data.messages || []).forEach(m => { addMessage(m); lastMessageId = Math.max(lastMessageId, Number(m.id)); });
      messagesEl.scrollTop = messagesEl.scrollHeight;
      await loadList(false);
    } catch (e) {
      if (initial) messagesEl.textContent = e.message;
    } finally { loadingMessages = false; }
  }
  async function loadList(keepSelection = true) {
    const data = await getJson(api + '?action=list');
    conversations = data.conversations || [];
    renderList();
    if (!keepSelection && selectedId) {
      const current = conversations.find(c => Number(c.id) === selectedId);
      if (!current) clearSelection();
      else updateHeader(current);
    }
  }
  function updateHeader(c) {
    personEl.hidden = false;
    personEl.replaceChildren();
    const strong = document.createElement('strong'); strong.textContent = c.visitor_name;
    const small = document.createElement('small'); small.textContent = [c.visitor_phone, c.visitor_email].filter(Boolean).join(' · ');
    personEl.append(strong, small);
    statusEl.hidden = false; statusEl.textContent = c.status === 'closed' ? 'Closed' : 'Open'; statusEl.classList.toggle('closed', c.status === 'closed');
    statusButton.hidden = false; statusButton.textContent = c.status === 'closed' ? 'Reopen chat' : 'Close chat';
    replyForm.hidden = false;
  }
  async function selectConversation(id) {
    selectedId = id; lastMessageId = 0;
    const current = conversations.find(c => Number(c.id) === id);
    if (current) updateHeader(current);
    messagesEl.replaceChildren(); const loading = document.createElement('div'); loading.className='ts-chat-empty'; loading.textContent='Loading messages…'; messagesEl.append(loading);
    renderList(); await loadMessages(true);
  }
  function clearSelection() {
    selectedId = 0; lastMessageId = 0; personEl.hidden = true; replyForm.hidden = true; statusEl.hidden = true; statusButton.hidden = true;
    messagesEl.replaceChildren(); const empty = document.createElement('div'); empty.className='ts-chat-empty'; empty.textContent='Select a visitor conversation to read and reply.'; messagesEl.append(empty);
  }
  replyForm.addEventListener('submit', async e => {
    e.preventDefault();
    const message = replyText.value.trim();
    if (!selectedId || !message) return;
    const submit = replyForm.querySelector('button'); submit.disabled = true;
    try {
      await getJson(api, {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':csrf}, body:JSON.stringify({action:'reply', conversation_id:selectedId, message})});
      replyText.value = ''; await loadMessages(false); await loadList(false);
    } catch (err) { alert(err.message); }
    finally { submit.disabled = false; replyText.focus(); }
  });
  statusButton.addEventListener('click', async () => {
    if (!selectedId) return;
    const current = conversations.find(c => Number(c.id) === selectedId);
    const next = current && current.status === 'closed' ? 'open' : 'closed';
    try {
      await getJson(api, {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':csrf}, body:JSON.stringify({action:'status', conversation_id:selectedId, status:next})});
      await loadList(false);
    } catch (e) { alert(e.message); }
  });
  document.getElementById('tsChatRefresh').addEventListener('click', () => loadList(false).then(() => loadMessages(false)).catch(e => alert(e.message)));
  loadList().then(() => { if (conversations.length) selectConversation(Number(conversations[0].id)); }).catch(e => { listEl.textContent = e.message + ' — open Database setup if tables are not installed.'; });
  window.setInterval(() => {
    if (document.hidden) return;
    loadList(false).then(() => loadMessages(false)).catch(() => {});
  }, 5000);
})();
</script>
<?php ts_admin_page_end(); ?>
