<?php
require_once '../config/db.php';
requireLogin();
if (!defined('PAGE_TITLE'))    define('PAGE_TITLE', 'Messages');
if (!defined('PAGE_SUBTITLE')) define('PAGE_SUBTITLE', 'Team Chat');
require_once '../includes/header.php';
?>
<div id="chatApp" style="display:grid;grid-template-columns:300px 1fr;height:calc(100vh - 130px);gap:0;background:#fff;border-radius:12px;box-shadow:var(--shadow);overflow:hidden;">

  <!-- LEFT: Sidebar -->
  <div style="border-right:1px solid var(--border);display:flex;flex-direction:column;background:#f8f9fa;">
    <!-- Header -->
    <div style="padding:15px;border-bottom:1px solid var(--border);background:#fff;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <strong style="font-size:15px;">Messages</strong>
        <div style="display:flex;gap:6px;">
          <button onclick="showNewChat()" class="btn btn-primary btn-sm btn-icon" title="New Chat"><i class="fas fa-edit"></i></button>
          <button onclick="showNewGroup()" class="btn btn-outline btn-sm btn-icon" title="New Group"><i class="fas fa-users"></i></button>
        </div>
      </div>
      <div style="position:relative;">
        <i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:12px;"></i>
        <input type="text" id="roomSearch" placeholder="Search conversations..." onkeyup="filterRooms()" style="width:100%;padding:8px 10px 8px 30px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;outline:none;">
      </div>
    </div>
    <!-- Online Users Strip -->
    <div id="onlineStrip" style="padding:10px 15px;border-bottom:1px solid var(--border);background:#fff;display:flex;gap:8px;overflow-x:auto;min-height:60px;align-items:center;">
      <div style="font-size:11px;color:var(--text-muted);white-space:nowrap;">Online:</div>
    </div>
    <!-- Room List -->
    <div id="roomList" style="flex:1;overflow-y:auto;"></div>
  </div>

  <!-- RIGHT: Chat Area -->
  <div style="display:flex;flex-direction:column;">
    <!-- Chat Header -->
    <div id="chatHeader" style="padding:15px 20px;border-bottom:1px solid var(--border);background:#fff;display:flex;align-items:center;gap:12px;min-height:65px;">
      <div style="color:var(--text-muted);font-size:14px;">Select a conversation to start chatting</div>
    </div>
    <!-- Messages -->
    <div id="messagesArea" style="flex:1;overflow-y:auto;padding:20px;background:#f0f4f8;display:flex;flex-direction:column;gap:10px;">
      <div style="text-align:center;color:var(--text-muted);margin-top:50px;">
        <div style="font-size:48px;margin-bottom:10px;">💬</div>
        <p>Select a conversation from the left</p>
      </div>
    </div>
    <!-- Input -->
    <div id="chatInput" style="padding:15px 20px;border-top:1px solid var(--border);background:#fff;display:none;">
      <div style="display:flex;gap:10px;align-items:flex-end;">
        <textarea id="msgInput" placeholder="Type a message... (Enter to send, Shift+Enter for new line)" style="flex:1;padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:14px;resize:none;outline:none;max-height:120px;min-height:44px;font-family:inherit;" rows="1" onkeydown="handleKey(event)"></textarea>
        <button onclick="sendMessage()" class="btn btn-primary" style="padding:10px 18px;border-radius:10px;"><i class="fas fa-paper-plane"></i></button>
      </div>
    </div>
  </div>
</div>

<!-- New Chat Modal -->
<div id="newChatModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:3000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;width:420px;max-height:80vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
    <div style="padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
      <strong>New Direct Message</strong>
      <button onclick="closeModals()" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);">&times;</button>
    </div>
    <div style="padding:15px;">
      <input type="text" id="userSearch" placeholder="Search users..." oninput="searchUsers(this.value)" style="width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;outline:none;">
    </div>
    <div id="userResults" style="flex:1;overflow-y:auto;padding:0 15px 15px;max-height:350px;"></div>
  </div>
</div>

<!-- New Group Modal -->
<div id="newGroupModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:3000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;width:450px;max-height:80vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
    <div style="padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
      <strong>Create Group Chat</strong>
      <button onclick="closeModals()" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);">&times;</button>
    </div>
    <div style="padding:15px;display:flex;flex-direction:column;gap:10px;">
      <input type="text" id="groupName" placeholder="Group name..." style="width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;outline:none;">
      <input type="text" id="groupUserSearch" placeholder="Search and add members..." oninput="searchGroupUsers(this.value)" style="width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;outline:none;">
      <div id="selectedMembers" style="display:flex;flex-wrap:wrap;gap:6px;min-height:30px;"></div>
    </div>
    <div id="groupUserResults" style="flex:1;overflow-y:auto;padding:0 15px;max-height:250px;"></div>
    <div style="padding:15px;border-top:1px solid var(--border);">
      <button onclick="createGroup()" class="btn btn-primary w-100" style="justify-content:center;"><i class="fas fa-users"></i> Create Group</button>
    </div>
  </div>
</div>

<script>
var currentRoomId = null;
var lastMsgTime   = '2000-01-01 00:00:00';
var pollInterval  = null;
var selectedGroupMembers = {};
var myId = <?= $_SESSION['user_id'] ?>;
var myName = <?= json_encode($_SESSION['user_name']) ?>;

// ===== INIT =====
loadRooms();
loadOnlineUsers();
setInterval(loadOnlineUsers, 30000);
setInterval(function() { if (currentRoomId) pollMessages(); }, 3000);

// ===== ROOMS =====
function loadRooms() {
    fetch('/pharmacy/chat/api.php?action=get_rooms')
    .then(function(r){ return r.json(); })
    .then(function(d) {
        if (!d.success) return;
        var html = '';
        d.rooms.forEach(function(room) {
            var icon = room.type === 'direct' ? '👤' : (room.type === 'broadcast' ? '📢' : '👥');
            var unread = room.unread_count > 0 ? '<span style="background:var(--danger);color:#fff;font-size:10px;padding:2px 6px;border-radius:10px;font-weight:700;">'+room.unread_count+'</span>' : '';
            var lastMsg = room.last_message ? '<div style="font-size:11px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;">'+(room.last_sender ? room.last_sender+': ':'')+escHtml(room.last_message)+'</div>' : '<div style="font-size:11px;color:var(--text-muted);">No messages yet</div>';
            var time = room.last_message_time ? '<div style="font-size:10px;color:var(--text-muted);">'+formatTime(room.last_message_time)+'</div>' : '';
            html += '<div class="room-item" data-id="'+room.id+'" data-name="'+escHtml(room.name)+'" onclick="openRoom('+room.id+','+JSON.stringify(escHtml(room.name))+')" style="padding:12px 15px;cursor:pointer;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:10px;transition:background 0.15s;'+(currentRoomId==room.id?'background:var(--primary-light);':'')+'" onmouseover="this.style.background=\'var(--light)\'" onmouseout="this.style.background=\''+(currentRoomId==room.id?'var(--primary-light)':'#f8f9fa')+'\'">'+
                '<div style="width:40px;height:40px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">'+icon+'</div>'+
                '<div style="flex:1;min-width:0;">'+
                    '<div style="display:flex;align-items:center;justify-content:space-between;"><strong style="font-size:13px;">'+escHtml(room.name)+'</strong>'+time+'</div>'+
                    '<div style="display:flex;align-items:center;justify-content:space-between;">'+lastMsg+unread+'</div>'+
                '</div></div>';
        });
        document.getElementById('roomList').innerHTML = html || '<div style="padding:20px;text-align:center;color:var(--text-muted);">No conversations yet</div>';
    });
}

function filterRooms() {
    var q = document.getElementById('roomSearch').value.toLowerCase();
    document.querySelectorAll('.room-item').forEach(function(el) {
        el.style.display = el.dataset.name.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ===== OPEN ROOM =====
function openRoom(roomId, roomName) {
    currentRoomId = roomId;
    lastMsgTime   = '2000-01-01 00:00:00';
    document.getElementById('chatHeader').innerHTML = '<div style="width:42px;height:42px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;">💬</div><div><div style="font-weight:700;font-size:15px;">'+roomName+'</div><div id="roomMeta" style="font-size:12px;color:var(--text-muted);">Loading...</div></div>';
    document.getElementById('chatInput').style.display = 'block';
    document.getElementById('messagesArea').innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);">Loading messages...</div>';
    loadMessages(true);
    loadRooms();
}

// ===== MESSAGES =====
function loadMessages(initial) {
    if (!currentRoomId) return;
    var since = initial ? '2000-01-01 00:00:00' : lastMsgTime;
    fetch('/pharmacy/chat/api.php?action=get_messages&room_id='+currentRoomId+'&since='+encodeURIComponent(since))
    .then(function(r){ return r.json(); })
    .then(function(d) {
        if (!d.success) return;
        if (d.room) {
            document.getElementById('roomMeta').textContent = d.room.member_count + ' members · ' + d.room.type;
        }
        if (initial) {
            document.getElementById('messagesArea').innerHTML = '';
        }
        d.messages.forEach(function(msg) {
            appendMessage(msg);
            lastMsgTime = msg.created_at;
        });
        if (initial || d.messages.length > 0) {
            scrollToBottom();
        }
    });
}

function pollMessages() { loadMessages(false); }

function appendMessage(msg) {
    var isMe = msg.sender_id == myId;
    var time = new Date(msg.created_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
    var avatar = '<div style="width:32px;height:32px;border-radius:50%;background:'+(isMe?'var(--primary)':'#6c757d')+';color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0;">'+msg.sender_name.charAt(0).toUpperCase()+'</div>';
    var bubble = '<div style="max-width:70%;background:'+(isMe?'var(--primary)':'#fff')+';color:'+(isMe?'#fff':'var(--text)')+';padding:10px 14px;border-radius:'+(isMe?'14px 14px 4px 14px':'14px 14px 14px 4px')+';box-shadow:0 1px 4px rgba(0,0,0,0.1);word-break:break-word;">'+
        (!isMe ? '<div style="font-size:10px;font-weight:700;color:var(--primary);margin-bottom:4px;">'+escHtml(msg.sender_name)+'</div>' : '')+
        '<div style="font-size:14px;line-height:1.5;">'+escHtml(msg.message)+'</div>'+
        '<div style="font-size:10px;opacity:0.7;margin-top:4px;text-align:right;">'+time+'</div>'+
    '</div>';
    var row = document.createElement('div');
    row.style.cssText = 'display:flex;align-items:flex-end;gap:8px;'+(isMe?'flex-direction:row-reverse;':'');
    row.innerHTML = avatar + bubble;
    document.getElementById('messagesArea').appendChild(row);
}

function sendMessage() {
    var input = document.getElementById('msgInput');
    var msg   = input.value.trim();
    if (!msg || !currentRoomId) return;
    input.value = '';
    input.style.height = 'auto';
    var fd = new FormData();
    fd.append('action', 'send');
    fd.append('room_id', currentRoomId);
    fd.append('message', msg);
    fetch('/pharmacy/chat/api.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d) {
        if (d.success) { appendMessage(d.message); scrollToBottom(); lastMsgTime = d.message.created_at; loadRooms(); }
    });
}

function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    var ta = document.getElementById('msgInput');
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
}

function scrollToBottom() {
    var area = document.getElementById('messagesArea');
    area.scrollTop = area.scrollHeight;
}

// ===== ONLINE USERS =====
function loadOnlineUsers() {
    fetch('/pharmacy/chat/api.php?action=online_users')
    .then(function(r){ return r.json(); })
    .then(function(d) {
        if (!d.success) return;
        var strip = document.getElementById('onlineStrip');
        if (d.users.length === 0) {
            strip.innerHTML = '<div style="font-size:11px;color:var(--text-muted);">No one else online</div>';
            return;
        }
        var html = '<div style="font-size:11px;color:var(--text-muted);white-space:nowrap;">Online:</div>';
        d.users.forEach(function(u) {
            html += '<div onclick="startDirect('+u.id+','+JSON.stringify(escHtml(u.name))+')" title="'+escHtml(u.name)+'" style="cursor:pointer;position:relative;flex-shrink:0;">'+
                '<div style="width:34px;height:34px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;">'+u.name.charAt(0).toUpperCase()+'</div>'+
                '<div style="position:absolute;bottom:0;right:0;width:10px;height:10px;background:#2ecc71;border-radius:50%;border:2px solid #fff;"></div>'+
            '</div>';
        });
        strip.innerHTML = html;
    });
}

// ===== NEW CHAT =====
function showNewChat() {
    document.getElementById('newChatModal').style.display = 'flex';
    document.getElementById('userSearch').value = '';
    searchUsers('');
}

function searchUsers(q) {
    fetch('/pharmacy/chat/api.php?action=get_users&search='+encodeURIComponent(q))
    .then(function(r){ return r.json(); })
    .then(function(d) {
        if (!d.success) return;
        var html = '';
        d.users.forEach(function(u) {
            var online = u.is_online == 1;
            html += '<div onclick="startDirect('+u.id+','+JSON.stringify(escHtml(u.name))+')" style="display:flex;align-items:center;gap:12px;padding:10px;border-radius:8px;cursor:pointer;margin-bottom:4px;" onmouseover="this.style.background=\'var(--light)\'" onmouseout="this.style.background=\'\'">'+
                '<div style="position:relative;"><div style="width:38px;height:38px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;">'+u.name.charAt(0).toUpperCase()+'</div>'+
                (online ? '<div style="position:absolute;bottom:0;right:0;width:10px;height:10px;background:#2ecc71;border-radius:50%;border:2px solid #fff;"></div>' : '')+'</div>'+
                '<div><div style="font-weight:600;font-size:13px;">'+escHtml(u.name)+'</div><div style="font-size:11px;color:var(--text-muted);">'+escHtml(u.role)+(u.branch_name?' · '+escHtml(u.branch_name):'')+'</div></div>'+
                '<div style="margin-left:auto;"><span style="font-size:10px;padding:2px 8px;border-radius:10px;background:'+(online?'#e8f8f0':'#f0f0f0')+';color:'+(online?'var(--secondary)':'var(--text-muted)')+';">'+(online?'Online':'Offline')+'</span></div>'+
            '</div>';
        });
        document.getElementById('userResults').innerHTML = html || '<div style="padding:15px;text-align:center;color:var(--text-muted);">No users found</div>';
    });
}

function startDirect(userId, userName) {
    closeModals();
    var fd = new FormData();
    fd.append('action', 'start_direct');
    fd.append('target_user_id', userId);
    fetch('/pharmacy/chat/api.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d) {
        if (d.success) { loadRooms(); setTimeout(function(){ openRoom(d.room_id, userName); }, 300); }
    });
}

// ===== NEW GROUP =====
function showNewGroup() {
    document.getElementById('newGroupModal').style.display = 'flex';
    selectedGroupMembers = {};
    document.getElementById('selectedMembers').innerHTML = '';
    document.getElementById('groupName').value = '';
    document.getElementById('groupUserSearch').value = '';
    searchGroupUsers('');
}

function searchGroupUsers(q) {
    fetch('/pharmacy/chat/api.php?action=get_users&search='+encodeURIComponent(q))
    .then(function(r){ return r.json(); })
    .then(function(d) {
        if (!d.success) return;
        var html = '';
        d.users.forEach(function(u) {
            var sel = selectedGroupMembers[u.id];
            html += '<div onclick="toggleGroupMember('+u.id+','+JSON.stringify(escHtml(u.name))+')" style="display:flex;align-items:center;gap:10px;padding:8px;border-radius:8px;cursor:pointer;background:'+(sel?'var(--primary-light)':'')+';" onmouseover="this.style.background=\'var(--light)\'" onmouseout="this.style.background=\''+(sel?'var(--primary-light)':'')+'\'">'+
                '<div style="width:34px;height:34px;border-radius:50%;background:'+(sel?'var(--primary)':'#6c757d')+';color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;">'+u.name.charAt(0).toUpperCase()+'</div>'+
                '<div style="flex:1;"><div style="font-weight:600;font-size:13px;">'+escHtml(u.name)+'</div><div style="font-size:11px;color:var(--text-muted);">'+escHtml(u.role)+'</div></div>'+
                (sel ? '<i class="fas fa-check-circle" style="color:var(--primary);"></i>' : '')+
            '</div>';
        });
        document.getElementById('groupUserResults').innerHTML = html;
    });
}

function toggleGroupMember(id, name) {
    if (selectedGroupMembers[id]) { delete selectedGroupMembers[id]; }
    else { selectedGroupMembers[id] = name; }
    var chips = '';
    Object.keys(selectedGroupMembers).forEach(function(id) {
        chips += '<span style="background:var(--primary);color:#fff;padding:4px 10px;border-radius:20px;font-size:12px;display:flex;align-items:center;gap:5px;">'+escHtml(selectedGroupMembers[id])+'<span onclick="toggleGroupMember('+id+',\'\')" style="cursor:pointer;font-weight:700;">&times;</span></span>';
    });
    document.getElementById('selectedMembers').innerHTML = chips;
    searchGroupUsers(document.getElementById('groupUserSearch').value);
}

function createGroup() {
    var name = document.getElementById('groupName').value.trim();
    if (!name) { alert('Please enter a group name'); return; }
    var members = Object.keys(selectedGroupMembers);
    var fd = new FormData();
    fd.append('action', 'create_group');
    fd.append('name', name);
    fd.append('members', JSON.stringify(members));
    fetch('/pharmacy/chat/api.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d) {
        if (d.success) { closeModals(); loadRooms(); setTimeout(function(){ openRoom(d.room_id, name); }, 300); }
    });
}

function closeModals() {
    document.getElementById('newChatModal').style.display = 'none';
    document.getElementById('newGroupModal').style.display = 'none';
}

// ===== HELPERS =====
function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatTime(ts) {
    if (!ts) return '';
    var d = new Date(ts);
    var now = new Date();
    if (d.toDateString() === now.toDateString()) return d.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
    return d.toLocaleDateString([],{month:'short',day:'numeric'});
}

// Close modals on backdrop click
document.getElementById('newChatModal').addEventListener('click', function(e){ if(e.target===this) closeModals(); });
document.getElementById('newGroupModal').addEventListener('click', function(e){ if(e.target===this) closeModals(); });
</script>

<?php require_once '../includes/footer.php'; ?>
