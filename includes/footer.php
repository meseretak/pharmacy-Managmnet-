    </main><!-- end page-content -->
</div><!-- end main-wrapper -->

<script>
// Auto-dismiss alerts
document.querySelectorAll('.alert').forEach(a => {
    setTimeout(() => { a.style.opacity='0'; a.style.transition='opacity 0.5s'; setTimeout(()=>a.remove(),500); }, 4000);
});
// Mobile menu
const menuToggle = document.getElementById('menuToggle');
if (menuToggle) {
    menuToggle.style.display = 'block';
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar.contains(e.target) && e.target !== menuToggle) sidebar.classList.remove('open');
    });
}
</script>

<?php if (isLoggedIn()): ?>
<!-- ===== WHATSAPP-STYLE CHAT SYSTEM ===== -->
<style>
/* Floating Button */
#chatFloatBtn {
    position: fixed; bottom: 24px; right: 24px; z-index: 9000;
    width: 58px; height: 58px; border-radius: 50%;
    background: #25d366; color: #fff; border: none; cursor: pointer;
    box-shadow: 0 4px 20px rgba(37,211,102,0.5);
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; transition: transform 0.2s, box-shadow 0.2s;
}
#chatFloatBtn:hover { transform: scale(1.1); box-shadow: 0 6px 25px rgba(37,211,102,0.6); }
#chatFloatBadge {
    display: none; position: absolute; top: -4px; right: -4px;
    background: #e53935; color: #fff; font-size: 10px; font-weight: 700;
    padding: 2px 5px; border-radius: 10px; min-width: 18px; text-align: center;
}
/* Overlay */
#chatOverlay {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.35);
}
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
@keyframes slideIn { from { transform:translateX(100%); } to { transform:translateX(0); } }
/* Main container */
#chatContainer {
    position: fixed; right: 0; top: 0; bottom: 0;
    width: 560px; max-width: 100vw;
    background: #fff; display: flex;
    box-shadow: -5px 0 40px rgba(0,0,0,0.25);
    animation: slideIn 0.25s ease;
    overflow: hidden;
}
/* LEFT SIDEBAR */
#chatSidebar {
    width: 240px; min-width: 200px; border-right: 1px solid #e9edef;
    display: flex; flex-direction: column; background: #fff;
}
.chat-sidebar-header {
    background: #075e54; color: #fff;
    padding: 10px 12px; display: flex; align-items: center; justify-content: space-between;
}
.chat-sidebar-header h3 { margin: 0; font-size: 15px; font-weight: 600; }
.chat-sidebar-header-actions { display: flex; gap: 8px; }
.chat-icon-btn {
    background: none; border: none; color: rgba(255,255,255,0.85);
    cursor: pointer; font-size: 18px; padding: 6px; border-radius: 50%;
    transition: background 0.15s;
}
.chat-icon-btn:hover { background: rgba(255,255,255,0.15); color: #fff; }
/* Search */
.chat-search-wrap { padding: 8px 12px; background: #f0f2f5; }
.chat-search-wrap input {
    width: 100%; padding: 8px 14px 8px 36px; border: none; border-radius: 8px;
    background: #fff; font-size: 14px; outline: none; color: #333;
    box-sizing: border-box;
}
.chat-search-icon { position: absolute; left: 24px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 13px; }
/* Online strip */
#chatOnlineStrip {
    padding: 5px 10px; background: #f0f2f5; border-bottom: 1px solid #e9edef;
    display: flex; gap: 8px; overflow-x: auto; align-items: center;
    min-height: 44px;
}
#chatOnlineStrip::-webkit-scrollbar { height: 3px; }
.online-avatar-wrap { display: flex; flex-direction: column; align-items: center; gap: 3px; cursor: pointer; flex-shrink: 0; }
.online-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; color: #fff; position: relative;
    border: 2px solid #25d366;
}
.online-dot {
    position: absolute; bottom: 1px; right: 1px;
    width: 10px; height: 10px; border-radius: 50%;
    background: #25d366; border: 2px solid #fff;
}
.online-name { font-size: 10px; color: #555; max-width: 44px; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
/* Room list */
#chatRoomList { flex: 1; overflow-y: auto; }
#chatRoomList::-webkit-scrollbar { width: 4px; }
#chatRoomList::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
.chat-room-item {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px; cursor: pointer; border-bottom: 1px solid #f0f2f5;
    transition: background 0.15s; position: relative;
}
.chat-room-item:hover { background: #f5f6f6; }
.chat-room-item.active { background: #ebebeb; }
.room-avatar {
    width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; color: #fff;
}
.room-info { flex: 1; min-width: 0; }
.room-name-row { display: flex; align-items: center; justify-content: space-between; }
.room-name { font-size: 13px; font-weight: 500; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px; }
.room-time { font-size: 10px; color: #aaa; flex-shrink: 0; }
.room-last-row { display: flex; align-items: center; justify-content: space-between; margin-top: 1px; }
.room-last-msg { font-size: 11px; color: #667781; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px; }
.room-unread {
    background: #25d366; color: #fff; font-size: 10px; font-weight: 700;
    padding: 1px 5px; border-radius: 10px; min-width: 16px; text-align: center; flex-shrink: 0;
}
</style>

<style>
/* RIGHT CHAT AREA */
#chatMain { flex: 1; display: flex; flex-direction: column; background: #efeae2; min-width: 0; min-height: 0; overflow: hidden; }
#chatMainEmpty {
    flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
    background: #f0f2f5; color: #aaa; overflow: hidden;
}
#chatMainEmpty i { font-size: 64px; margin-bottom: 16px; color: #ccc; }
#chatMainEmpty p { font-size: 16px; }
/* Chat header */
#chatHeader {
    background: #075e54; color: #fff;
    padding: 8px 12px; display: flex; align-items: center; gap: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
#chatHeaderAvatar { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #fff; flex-shrink: 0; }
#chatHeaderInfo { flex: 1; }
#chatHeaderName { font-size: 14px; font-weight: 600; }
#chatHeaderSub { font-size: 11px; color: rgba(255,255,255,0.75); }
/* Messages area */
#chatMessages {
    flex: 1; overflow-y: auto; padding: 10px 12px;
    display: flex; flex-direction: column; gap: 2px;
    justify-content: flex-start;
    background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23c8c8c8' fill-opacity='0.15'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
#chatMessages::-webkit-scrollbar { width: 5px; }
#chatMessages::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 4px; }
/* Message bubbles */
.msg-wrap { display: flex; flex-direction: column; margin-bottom: 2px; }
.msg-wrap.mine { align-items: flex-end; }
.msg-wrap.theirs { align-items: flex-start; }
.msg-bubble {
    max-width: 75%; padding: 5px 9px 4px; border-radius: 8px;
    font-size: 13px; line-height: 1.4; position: relative;
    box-shadow: 0 1px 2px rgba(0,0,0,0.12); word-break: break-word;
}
.msg-wrap.mine .msg-bubble { background: #dcf8c6; border-radius: 8px 0 8px 8px; }
.msg-wrap.theirs .msg-bubble { background: #fff; border-radius: 0 8px 8px 8px; }
.msg-sender { font-size: 12px; font-weight: 600; margin-bottom: 2px; }
.msg-text { color: #111; }
.msg-meta { display: flex; align-items: center; justify-content: flex-end; gap: 4px; margin-top: 3px; }
.msg-time { font-size: 11px; color: #999; }
.msg-date-divider {
    text-align: center; margin: 12px 0;
    font-size: 12px; color: #667781;
}
.msg-date-divider span {
    background: rgba(255,255,255,0.85); padding: 4px 12px; border-radius: 8px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}
/* Input area */
#chatInputArea {
    background: #f0f2f5; padding: 7px 10px;
    display: flex; align-items: flex-end; gap: 7px;
}
#chatInputArea textarea {
    flex: 1; border: none; border-radius: 18px; padding: 7px 12px;
    font-size: 13px; resize: none; outline: none; max-height: 80px;
    background: #fff; line-height: 1.4; font-family: inherit;
}
#chatSendBtn {
    width: 36px; height: 36px; border-radius: 50%; background: #075e54;
    color: #fff; border: none; cursor: pointer; font-size: 15px;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s; flex-shrink: 0;
}
#chatSendBtn:hover { background: #128c7e; }
/* Modals */
.chat-modal-overlay {
    display: none; position: fixed; inset: 0; z-index: 10000;
    background: rgba(0,0,0,0.5); align-items: center; justify-content: center;
}
.chat-modal-overlay.open { display: flex; }
.chat-modal {
    background: #fff; border-radius: 12px; width: 420px; max-width: 95vw;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3); overflow: hidden;
}
.chat-modal-header {
    background: #075e54; color: #fff; padding: 16px 20px;
    display: flex; align-items: center; justify-content: space-between;
}
.chat-modal-header h4 { margin: 0; font-size: 16px; }
.chat-modal-body { padding: 20px; }
.chat-modal-body input, .chat-modal-body textarea {
    width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px;
    font-size: 14px; outline: none; box-sizing: border-box; margin-bottom: 12px;
}
.chat-modal-body input:focus, .chat-modal-body textarea:focus { border-color: #075e54; }
.chat-modal-footer { padding: 12px 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 8px; }
.btn-chat-primary { background: #075e54; color: #fff; border: none; padding: 9px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; }
.btn-chat-primary:hover { background: #128c7e; }
.btn-chat-cancel { background: #f0f2f5; color: #333; border: none; padding: 9px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; }
.user-search-result {
    display: flex; align-items: center; gap: 10px; padding: 10px 12px;
    border-radius: 8px; cursor: pointer; transition: background 0.15s;
}
.user-search-result:hover { background: #f0f2f5; }
.user-search-result.selected { background: #e7f5ef; }
.user-result-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; color: #fff; flex-shrink: 0; }
#userSearchResults, #groupMemberResults { max-height: 220px; overflow-y: auto; margin-top: 4px; }
.selected-members { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
.selected-chip {
    background: #e7f5ef; color: #075e54; padding: 4px 10px; border-radius: 16px;
    font-size: 12px; display: flex; align-items: center; gap: 5px;
}
.selected-chip button { background: none; border: none; cursor: pointer; color: #075e54; font-size: 14px; padding: 0; line-height: 1; }
/* Responsive */
@media (max-width: 700px) {
    #chatContainer { width: 100vw; }
    #chatSidebar { width: 100%; }
    #chatMain { display: none; }
    #chatSidebar.room-open { display: none; }
    #chatMain.room-open { display: flex; width: 100%; }
}
</style>

<!-- Floating Chat Button -->
<div id="chatFloatBtn" onclick="toggleChat()" title="Messages" style="position:fixed;bottom:24px;right:24px;z-index:9000;width:58px;height:58px;border-radius:50%;background:#25d366;color:#fff;border:none;cursor:pointer;box-shadow:0 4px 20px rgba(37,211,102,0.5);display:flex;align-items:center;justify-content:center;font-size:24px;transition:transform 0.2s;">
    <i class="fas fa-comment-dots"></i>
    <span id="chatFloatBadge" style="display:none;position:absolute;top:-4px;right:-4px;background:#e53935;color:#fff;font-size:10px;font-weight:700;padding:2px 5px;border-radius:10px;min-width:18px;text-align:center;"></span>
</div>

<!-- Full Chat Overlay -->
<div id="chatOverlay" onclick="handleOverlayClick(event)">
  <div id="chatContainer">

    <!-- LEFT: Conversations List -->
    <div id="chatSidebar">
      <div class="chat-sidebar-header">
        <h3><i class="fas fa-comment-dots" style="margin-right:8px;"></i>Messages</h3>
        <div class="chat-sidebar-header-actions">
          <button class="chat-icon-btn" onclick="openNewGroupModal()" title="New Group"><i class="fas fa-users"></i></button>
          <button class="chat-icon-btn" onclick="openNewDMModal()" title="New Message"><i class="fas fa-edit"></i></button>
          <button class="chat-icon-btn" onclick="toggleChat()" title="Close"><i class="fas fa-times"></i></button>
        </div>
      </div>
      <div style="position:relative;" class="chat-search-wrap">
        <i class="fas fa-search chat-search-icon"></i>
        <input type="text" id="roomSearch" placeholder="Search conversations..." oninput="filterRooms(this.value)">
      </div>
      <div id="chatOnlineStrip"></div>
      <div id="chatRoomList"><div style="padding:30px;text-align:center;color:#aaa;font-size:13px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div></div>
    </div>

    <!-- RIGHT: Active Chat -->
    <div id="chatMain">
      <div id="chatMainEmpty">
        <i class="fas fa-comments"></i>
        <p>Select a conversation to start chatting</p>
        <p style="font-size:13px;">or <a href="#" onclick="openNewDMModal();return false;" style="color:#075e54;">start a new message</a></p>
      </div>
      <div id="chatActiveArea" style="display:none;flex:1;flex-direction:column;min-height:0;overflow:hidden;">
        <div id="chatHeader">
          <div id="chatHeaderAvatar"></div>
          <div id="chatHeaderInfo">
            <div id="chatHeaderName"></div>
            <div id="chatHeaderSub"></div>
          </div>
          <button class="chat-icon-btn" onclick="closeActiveChat()" title="Back" style="margin-left:auto;"><i class="fas fa-arrow-left"></i></button>
        </div>
        <div id="chatMessages"></div>
        <div id="chatInputArea">
      <label for="chatFileInput" id="chatAttachBtn" title="Attach image or file (max 5MB)" style="width:38px;height:38px;border-radius:50%;background:#f0f2f5;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#555;font-size:16px;flex-shrink:0;transition:background 0.15s;" onmouseover="this.style.background='#e0e0e0'" onmouseout="this.style.background='#f0f2f5'">
        <i class="fas fa-paperclip"></i>
      </label>
      <input type="file" id="chatFileInput" accept="image/*,.pdf,.txt" style="display:none;" onchange="handleFileSelect(this)">
      <div style="flex:1;display:flex;flex-direction:column;gap:6px;">
        <div id="chatFilePreview" style="display:none;background:#fff;border:1px solid #ddd;border-radius:8px;padding:8px 10px;display:none;align-items:center;gap:8px;font-size:12px;">
          <span id="chatFilePreviewIcon" style="font-size:18px;"></span>
          <span id="chatFilePreviewName" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#333;"></span>
          <span id="chatFilePreviewSize" style="color:#aaa;flex-shrink:0;"></span>
          <button onclick="clearFileAttachment()" style="background:none;border:none;cursor:pointer;color:#e53935;font-size:14px;padding:0 2px;" title="Remove">&#x2715;</button>
        </div>
        <textarea id="chatInput" placeholder="Type a message..." rows="1" onkeydown="handleChatKey(event)" oninput="autoResizeTextarea(this)"></textarea>
      </div>
      <button id="chatSendBtn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
    </div>
      </div>
    </div>

  </div>
</div>

<!-- New DM Modal -->
<div class="chat-modal-overlay" id="newDMModal">
  <div class="chat-modal">
    <div class="chat-modal-header">
      <h4><i class="fas fa-user-plus" style="margin-right:8px;"></i>New Message</h4>
      <button class="chat-icon-btn" onclick="closeModal('newDMModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="chat-modal-body">
      <input type="text" id="dmSearchInput" placeholder="Search users..." oninput="searchUsersForDM(this.value)">
      <div id="userSearchResults"></div>
    </div>
  </div>
</div>

<!-- New Group Modal -->
<div class="chat-modal-overlay" id="newGroupModal">
  <div class="chat-modal">
    <div class="chat-modal-header">
      <h4><i class="fas fa-users" style="margin-right:8px;"></i>New Group</h4>
      <button class="chat-icon-btn" onclick="closeModal('newGroupModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="chat-modal-body">
      <input type="text" id="groupNameInput" placeholder="Group name...">
      <input type="text" id="groupMemberSearch" placeholder="Search members to add..." oninput="searchUsersForGroup(this.value)">
      <div class="selected-members" id="selectedMembersChips"></div>
      <div id="groupMemberResults"></div>
    </div>
    <div class="chat-modal-footer">
      <button class="btn-chat-cancel" onclick="closeModal('newGroupModal')">Cancel</button>
      <button class="btn-chat-primary" onclick="createGroup()"><i class="fas fa-check"></i> Create Group</button>
    </div>
  </div>
</div>

<script>
// ===== CHAT SYSTEM JS =====
var chatVisible = false;
var activeRoomId = null;
var allRooms = [];
var pollTimer = null;
var lastMsgTime = '2000-01-01 00:00:00';
var selectedGroupMembers = [];
var avatarColors = ['#075e54','#128c7e','#25d366','#e53935','#1565c0','#6a1b9a','#f57c00','#2e7d32','#ad1457','#00838f'];

function getAvatarColor(str) {
    var h = 0;
    for (var i = 0; i < str.length; i++) h = str.charCodeAt(i) + ((h << 5) - h);
    return avatarColors[Math.abs(h) % avatarColors.length];
}
function getInitials(name) {
    if (!name) return '?';
    var parts = name.trim().split(' ');
    return parts.length > 1 ? (parts[0][0] + parts[1][0]).toUpperCase() : parts[0][0].toUpperCase();
}
function formatTime(dt) {
    if (!dt) return '';
    var d = new Date(dt.replace(' ', 'T'));
    var now = new Date();
    var diff = (now - d) / 1000;
    if (diff < 60) return 'now';
    if (diff < 3600) return Math.floor(diff/60) + 'm';
    if (diff < 86400) return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
    return (d.getMonth()+1) + '/' + d.getDate();
}
function formatFullTime(dt) {
    if (!dt) return '';
    var d = new Date(dt.replace(' ', 'T'));
    return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
}
function formatDate(dt) {
    if (!dt) return '';
    var d = new Date(dt.replace(' ', 'T'));
    var now = new Date();
    var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    var msgDay = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    var diff = (today - msgDay) / 86400000;
    if (diff === 0) return 'Today';
    if (diff === 1) return 'Yesterday';
    return d.toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
}

function toggleChat() {
    chatVisible = !chatVisible;
    var overlay = document.getElementById('chatOverlay');
    overlay.style.display = chatVisible ? 'block' : 'none';
    if (chatVisible) {
        loadRooms();
        loadOnlineUsers();
        startPolling();
    } else {
        stopPolling();
    }
}

function handleOverlayClick(e) {
    if (e.target === document.getElementById('chatOverlay')) toggleChat();
}

function startPolling() {
    stopPolling();
    pollTimer = setInterval(function() {
        loadRooms(true);
        if (activeRoomId) pollMessages();
        loadOnlineUsers();
    }, 3000);
}
function stopPolling() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

// ---- ROOMS ----
function loadRooms(silent) {
    fetch('/pharmacy/chat/api.php?action=get_rooms')
    .then(r => r.json())
    .then(d => {
        if (!d.success) return;
        allRooms = d.rooms;
        renderRooms(allRooms);
        updateFloatBadge(allRooms);
    });
}

function renderRooms(rooms) {
    var html = '';
    if (!rooms.length) {
        html = '<div style="padding:30px;text-align:center;color:#aaa;font-size:13px;"><i class="fas fa-comment-slash" style="font-size:32px;margin-bottom:10px;display:block;"></i>No conversations yet</div>';
    }
    rooms.forEach(function(r) {
        var icon = r.type === 'direct' ? getInitials(r.name) : (r.type === 'broadcast' ? '📢' : getInitials(r.name));
        var color = getAvatarColor(r.name);
        var unread = r.unread_count > 0 ? '<span class="room-unread">' + r.unread_count + '</span>' : '';
        var lastMsg = r.last_message ? (r.last_sender ? '<b>' + escHtml(r.last_sender.split(' ')[0]) + ':</b> ' : '') + escHtml(r.last_message.substring(0,45)) : '<i>No messages yet</i>';
        var active = activeRoomId == r.id ? ' active' : '';
        html += '<div class="chat-room-item' + active + '" onclick="openRoom(' + r.id + ')" data-room-name="' + escHtml(r.name.toLowerCase()) + '">' +
            '<div class="room-avatar" style="background:' + color + ';">' + icon + '</div>' +
            '<div class="room-info">' +
              '<div class="room-name-row"><span class="room-name">' + escHtml(r.name) + '</span><span class="room-time">' + formatTime(r.last_message_time) + '</span></div>' +
              '<div class="room-last-row"><span class="room-last-msg">' + lastMsg + '</span>' + unread + '</div>' +
            '</div></div>';
    });
    document.getElementById('chatRoomList').innerHTML = html;
}

function filterRooms(q) {
    q = q.toLowerCase();
    var filtered = allRooms.filter(r => r.name.toLowerCase().includes(q));
    renderRooms(filtered);
}

function updateFloatBadge(rooms) {
    var total = rooms.reduce((s,r) => s + (parseInt(r.unread_count)||0), 0);
    var badge = document.getElementById('chatFloatBadge');
    if (total > 0) { badge.style.display = 'block'; badge.textContent = total > 99 ? '99+' : total; }
    else { badge.style.display = 'none'; }
}

// ---- ONLINE USERS ----
function loadOnlineUsers() {
    fetch('/pharmacy/chat/api.php?action=online_users')
    .then(r => r.json())
    .then(d => {
        if (!d.success) return;
        var strip = document.getElementById('chatOnlineStrip');
        if (!d.users.length) { strip.innerHTML = '<span style="font-size:12px;color:#aaa;">No one else online</span>'; return; }
        var html = '<span style="font-size:11px;color:#aaa;flex-shrink:0;">' + d.users.length + ' online</span>';
        d.users.forEach(function(u) {
            var color = getAvatarColor(u.name);
            html += '<div class="online-avatar-wrap" onclick="startDMWithUser(' + u.id + ',\'' + escHtml(u.name) + '\')" title="' + escHtml(u.name) + '">' +
                '<div class="online-avatar" style="background:' + color + ';">' + getInitials(u.name) + '<span class="online-dot"></span></div>' +
                '<span class="online-name">' + escHtml(u.name.split(' ')[0]) + '</span></div>';
        });
        strip.innerHTML = html;
    });
}

// ---- OPEN ROOM ----
function openRoom(roomId) {
    activeRoomId = roomId;
    lastMsgTime = '2000-01-01 00:00:00';
    document.getElementById('chatMainEmpty').style.display = 'none';
    var activeArea = document.getElementById('chatActiveArea');
    activeArea.style.display = 'flex';
    activeArea.style.flexDirection = 'column';
    document.getElementById('chatMessages').innerHTML = '<div style="text-align:center;padding:20px;color:#aaa;"><i class="fas fa-spinner fa-spin"></i></div>';
    // Update active state
    document.querySelectorAll('.chat-room-item').forEach(el => el.classList.remove('active'));
    var items = document.querySelectorAll('.chat-room-item');
    items.forEach(el => { if (el.onclick.toString().includes('(' + roomId + ')')) el.classList.add('active'); });
    // Find room info
    var room = allRooms.find(r => r.id == roomId);
    if (room) {
        var color = getAvatarColor(room.name);
        document.getElementById('chatHeaderAvatar').style.background = color;
        document.getElementById('chatHeaderAvatar').textContent = getInitials(room.name);
        document.getElementById('chatHeaderName').textContent = room.name;
        document.getElementById('chatHeaderSub').textContent = room.type === 'direct' ? 'Direct Message' : (room.type === 'broadcast' ? 'Broadcast' : 'Group');
    }
    loadMessages(true);
    document.getElementById('chatInput').focus();
    // Mobile
    document.getElementById('chatSidebar').classList.add('room-open');
    document.getElementById('chatMain').classList.add('room-open');
}

function closeActiveChat() {
    activeRoomId = null;
    document.getElementById('chatMainEmpty').style.display = 'flex';
    document.getElementById('chatActiveArea').style.display = 'none';
    document.getElementById('chatSidebar').classList.remove('room-open');
    document.getElementById('chatMain').classList.remove('room-open');
    document.querySelectorAll('.chat-room-item').forEach(el => el.classList.remove('active'));
}

// ---- MESSAGES ----
function loadMessages(initial) {
    if (!activeRoomId) return;
    var url = '/pharmacy/chat/api.php?action=get_messages&room_id=' + activeRoomId + '&since=' + encodeURIComponent(lastMsgTime);
    fetch(url)
    .then(r => r.json())
    .then(d => {
        if (!d.success) return;
        if (d.room) {
            document.getElementById('chatHeaderName').textContent = d.room.name;
            document.getElementById('chatHeaderSub').textContent = d.room.member_count + ' members';
        }
        if (initial) {
            renderMessages(d.messages, true);
        } else if (d.messages.length) {
            appendMessages(d.messages);
        }
        if (d.messages.length) {
            lastMsgTime = d.messages[d.messages.length-1].created_at;
        }
    });
}

function pollMessages() {
    if (!activeRoomId) return;
    var url = '/pharmacy/chat/api.php?action=get_messages&room_id=' + activeRoomId + '&since=' + encodeURIComponent(lastMsgTime);
    fetch(url)
    .then(r => r.json())
    .then(d => {
        if (!d.success || !d.messages.length) return;
        appendMessages(d.messages);
        lastMsgTime = d.messages[d.messages.length-1].created_at;
        loadRooms(true);
    });
}

var myUserId = <?= (int)($_SESSION['user_id'] ?? 0) ?>;

function renderMessages(messages, scroll) {
    var container = document.getElementById('chatMessages');
    if (!messages.length) { container.innerHTML = '<div style="text-align:center;padding:30px;color:#aaa;font-size:13px;"><i class="fas fa-comment-slash" style="font-size:32px;margin-bottom:10px;display:block;"></i>No messages yet. Say hello!</div>'; return; }
    var html = '';
    var lastDate = '';
    messages.forEach(function(m) {
        var msgDate = formatDate(m.created_at);
        if (msgDate !== lastDate) {
            html += '<div class="msg-date-divider"><span>' + msgDate + '</span></div>';
            lastDate = msgDate;
        }
        html += buildMsgHtml(m);
    });
    container.innerHTML = html;
    if (scroll) container.scrollTop = container.scrollHeight;
}

function appendMessages(messages) {
    var container = document.getElementById('chatMessages');
    var atBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 60;
    var lastDateEl = container.querySelector('.msg-date-divider:last-of-type');
    var lastDate = lastDateEl ? lastDateEl.textContent.trim() : '';
    messages.forEach(function(m) {
        var msgDate = formatDate(m.created_at);
        if (msgDate !== lastDate) {
            var div = document.createElement('div');
            div.className = 'msg-date-divider';
            div.innerHTML = '<span>' + msgDate + '</span>';
            container.appendChild(div);
            lastDate = msgDate;
        }
        var wrap = document.createElement('div');
        wrap.innerHTML = buildMsgHtml(m);
        container.appendChild(wrap.firstChild);
    });
    if (atBottom) container.scrollTop = container.scrollHeight;
}

function buildMsgHtml(m) {
    var mine = m.sender_id == myUserId;
    var color = getAvatarColor(m.sender_name);
    var senderHtml = !mine ? '<div class="msg-sender" style="color:' + color + ';">' + escHtml(m.sender_name) + '</div>' : '';

    // Build content based on message type
    var contentHtml = '';
    if (m.msg_type === 'image' && m.file_path) {
        contentHtml = '<div style="margin-bottom:4px;">' +
            '<img src="' + escHtml(m.file_path) + '" alt="' + escHtml(m.file_name || 'image') + '" ' +
            'style="max-width:220px;max-height:200px;border-radius:6px;display:block;cursor:pointer;" ' +
            'onclick="window.open(\'' + escHtml(m.file_path) + '\',\'_blank\')" ' +
            'onerror="this.style.display=\'none\'">' +
            (m.message && m.message !== m.file_name ? '<div style="margin-top:4px;font-size:13px;">' + escHtml(m.message) + '</div>' : '') +
            '</div>';
    } else if (m.msg_type === 'file' && m.file_path) {
        var sizeStr = m.file_size > 1048576 ? (m.file_size/1048576).toFixed(1)+'MB' : Math.round((m.file_size||0)/1024)+'KB';
        contentHtml = '<a href="' + escHtml(m.file_path) + '" target="_blank" download style="display:flex;align-items:center;gap:8px;text-decoration:none;color:inherit;background:rgba(0,0,0,0.05);padding:8px 10px;border-radius:6px;margin-bottom:4px;">' +
            '<span style="font-size:22px;">📄</span>' +
            '<div style="min-width:0;"><div style="font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px;">' + escHtml(m.file_name || 'File') + '</div>' +
            '<div style="font-size:11px;color:#888;">' + sizeStr + ' · tap to download</div></div>' +
            '<span style="margin-left:auto;font-size:16px;">⬇️</span></a>';
    } else {
        contentHtml = '<div class="msg-text">' + escHtml(m.message).replace(/\n/g,'<br>') + '</div>';
    }

    return '<div class="msg-wrap ' + (mine ? 'mine' : 'theirs') + '" data-msg-id="' + m.id + '">' +
        '<div class="msg-bubble">' +
          senderHtml +
          contentHtml +
          '<div class="msg-meta"><span class="msg-time">' + formatFullTime(m.created_at) + '</span>' +
          (mine ? '<i class="fas fa-check-double" style="font-size:10px;color:#4fc3f7;"></i>' : '') +
          '</div>' +
        '</div></div>';
}

// ---- FILE ATTACHMENT ----
var pendingFile = null;
function handleFileSelect(input) {
    var file = input.files[0];
    if (!file) return;
    var maxSize = 5 * 1024 * 1024;
    if (file.size > maxSize) {
        alert('File too large. Maximum size is 5MB.');
        input.value = '';
        return;
    }
    pendingFile = file;
    var preview = document.getElementById('chatFilePreview');
    var icon    = file.type.startsWith('image/') ? '🖼️' : (file.type === 'application/pdf' ? '📄' : '📎');
    var sizeStr = file.size > 1024*1024 ? (file.size/1024/1024).toFixed(1)+'MB' : Math.round(file.size/1024)+'KB';
    document.getElementById('chatFilePreviewIcon').textContent = icon;
    document.getElementById('chatFilePreviewName').textContent = file.name;
    document.getElementById('chatFilePreviewSize').textContent = sizeStr;
    preview.style.display = 'flex';
    document.getElementById('chatInput').focus();
}
function clearFileAttachment() {
    pendingFile = null;
    document.getElementById('chatFileInput').value = '';
    document.getElementById('chatFilePreview').style.display = 'none';
}

// ---- SEND ----
function sendMessage() {
    if (!activeRoomId) return;
    var input = document.getElementById('chatInput');
    var msg   = input.value.trim();
    if (!msg && !pendingFile) return;
    input.value = '';
    input.style.height = 'auto';
    var fd = new FormData();
    fd.append('action', 'send');
    fd.append('room_id', activeRoomId);
    fd.append('message', msg);
    if (pendingFile) {
        fd.append('attachment', pendingFile);
        clearFileAttachment();
    }
    fetch('/pharmacy/chat/api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            appendMessages([d.message]);
            lastMsgTime = d.message.created_at;
            loadRooms(true);
        } else {
            alert(d.error || 'Failed to send message');
        }
    });
}

function handleChatKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
}
function autoResizeTextarea(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 80) + 'px';
}

// Paste image from clipboard (screenshot paste)
document.addEventListener('paste', function(e) {
    if (!activeRoomId) return;
    var items = e.clipboardData && e.clipboardData.items;
    if (!items) return;
    for (var i = 0; i < items.length; i++) {
        if (items[i].type.startsWith('image/')) {
            var file = items[i].getAsFile();
            if (file) {
                // Create a named file from the blob
                var namedFile = new File([file], 'screenshot_' + Date.now() + '.png', { type: file.type });
                pendingFile = namedFile;
                var preview = document.getElementById('chatFilePreview');
                document.getElementById('chatFilePreviewIcon').textContent = '🖼️';
                document.getElementById('chatFilePreviewName').textContent = namedFile.name;
                var sizeStr = namedFile.size > 1024*1024 ? (namedFile.size/1024/1024).toFixed(1)+'MB' : Math.round(namedFile.size/1024)+'KB';
                document.getElementById('chatFilePreviewSize').textContent = sizeStr;
                preview.style.display = 'flex';
                e.preventDefault();
            }
        }
    }
});

// ---- NEW DM ----
function openNewDMModal() {
    document.getElementById('dmSearchInput').value = '';
    document.getElementById('userSearchResults').innerHTML = '';
    document.getElementById('newDMModal').classList.add('open');
    setTimeout(() => document.getElementById('dmSearchInput').focus(), 100);
    searchUsersForDM('');
}
function searchUsersForDM(q) {
    fetch('/pharmacy/chat/api.php?action=get_users&search=' + encodeURIComponent(q))
    .then(r => r.json())
    .then(d => {
        if (!d.success) return;
        var html = '';
        d.users.forEach(function(u) {
            var color = getAvatarColor(u.name);
            var onlineDot = u.is_online == 1 ? '<span style="width:8px;height:8px;border-radius:50%;background:#25d366;display:inline-block;margin-left:4px;"></span>' : '';
            html += '<div class="user-search-result" onclick="startDMWithUser(' + u.id + ',\'' + escHtml(u.name) + '\')">' +
                '<div class="user-result-avatar" style="background:' + color + ';">' + getInitials(u.name) + '</div>' +
                '<div><div style="font-size:14px;font-weight:500;">' + escHtml(u.name) + onlineDot + '</div>' +
                '<div style="font-size:12px;color:#aaa;">' + escHtml(u.role || '') + (u.branch_name ? ' · ' + escHtml(u.branch_name) : '') + '</div></div></div>';
        });
        document.getElementById('userSearchResults').innerHTML = html || '<div style="padding:16px;text-align:center;color:#aaa;font-size:13px;">No users found</div>';
    });
}
function startDMWithUser(userId, userName) {
    closeModal('newDMModal');
    var fd = new FormData();
    fd.append('action', 'start_direct');
    fd.append('target_user_id', userId);
    fetch('/pharmacy/chat/api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            loadRooms();
            setTimeout(() => openRoom(d.room_id), 400);
        }
    });
}

// ---- NEW GROUP ----
function openNewGroupModal() {
    selectedGroupMembers = [];
    document.getElementById('groupNameInput').value = '';
    document.getElementById('groupMemberSearch').value = '';
    document.getElementById('groupMemberResults').innerHTML = '';
    document.getElementById('selectedMembersChips').innerHTML = '';
    document.getElementById('newGroupModal').classList.add('open');
    setTimeout(() => document.getElementById('groupNameInput').focus(), 100);
}
function searchUsersForGroup(q) {
    fetch('/pharmacy/chat/api.php?action=get_users&search=' + encodeURIComponent(q))
    .then(r => r.json())
    .then(d => {
        if (!d.success) return;
        var html = '';
        d.users.forEach(function(u) {
            var color = getAvatarColor(u.name);
            var isSel = selectedGroupMembers.find(m => m.id == u.id);
            html += '<div class="user-search-result' + (isSel ? ' selected' : '') + '" onclick="toggleGroupMember(' + u.id + ',\'' + escHtml(u.name) + '\')">' +
                '<div class="user-result-avatar" style="background:' + color + ';">' + getInitials(u.name) + '</div>' +
                '<div style="font-size:14px;font-weight:500;">' + escHtml(u.name) + '</div>' +
                (isSel ? '<i class="fas fa-check" style="margin-left:auto;color:#075e54;"></i>' : '') +
                '</div>';
        });
        document.getElementById('groupMemberResults').innerHTML = html || '<div style="padding:16px;text-align:center;color:#aaa;font-size:13px;">No users found</div>';
    });
}
function toggleGroupMember(id, name) {
    var idx = selectedGroupMembers.findIndex(m => m.id == id);
    if (idx >= 0) selectedGroupMembers.splice(idx, 1);
    else selectedGroupMembers.push({id, name});
    renderSelectedChips();
    searchUsersForGroup(document.getElementById('groupMemberSearch').value);
}
function renderSelectedChips() {
    var html = '';
    selectedGroupMembers.forEach(function(m) {
        html += '<div class="selected-chip">' + escHtml(m.name) + '<button onclick="toggleGroupMember(' + m.id + ',\'' + escHtml(m.name) + '\')">&times;</button></div>';
    });
    document.getElementById('selectedMembersChips').innerHTML = html;
}
function createGroup() {
    var name = document.getElementById('groupNameInput').value.trim();
    if (!name) { alert('Please enter a group name'); return; }
    var fd = new FormData();
    fd.append('action', 'create_group');
    fd.append('name', name);
    fd.append('members', JSON.stringify(selectedGroupMembers.map(m => m.id)));
    fetch('/pharmacy/chat/api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            closeModal('newGroupModal');
            loadRooms();
            setTimeout(() => openRoom(d.room_id), 400);
        } else { alert(d.error || 'Failed to create group'); }
    });
}

// ---- UTILS ----
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Poll badge even when chat is closed
setInterval(function() {
    fetch('/pharmacy/chat/api.php?action=get_rooms')
    .then(r => r.json())
    .then(d => { if (d.success) updateFloatBadge(d.rooms); });
}, 30000);
// Initial badge
fetch('/pharmacy/chat/api.php?action=get_rooms')
.then(r => r.json())
.then(d => { if (d.success) updateFloatBadge(d.rooms); });
</script>
<?php endif; ?>
</body>
</html>
