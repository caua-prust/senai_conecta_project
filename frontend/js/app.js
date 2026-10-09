const API_URL = '../backend/index.php'; 
let currentUserId = null;

document.addEventListener("DOMContentLoaded", () => {
    checkSession();
    loadFeed();
});

function openModal(id) { 
    document.getElementById(id).style.display = 'block'; 
    document.getElementById('bg-modal').style.display = 'block';
}

function closeModal(id) { 
    document.getElementById(id).style.display = 'none'; 
    document.getElementById('bg-modal').style.display = 'none';
    if(id === 'loginModal') document.getElementById('loginError').innerText = '';
}

async function checkSession() {
    try {
        const res = await fetch(`${API_URL}?action=check_session`);
        const data = await res.json();
        
        if(data.autenticado) {
            currentUserId = data.user_id;
            document.getElementById('authArea').innerHTML = `
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="position: relative; cursor: pointer;" onclick="document.getElementById('changePicInput').click()">
                        <img src="../backend/uploads/${data.foto}" onerror="this.src='../backend/uploads/avatar.png'" class="avatar">
                    </div>
                    <strong>@${data.username}</strong> 
                    <button class="btn-danger" onclick="logout()">Sair</button>
                    <input type="file" id="changePicInput" style="display: none;" accept="image/*" onchange="atualizarFoto()">
                </div>`;
            document.getElementById('publishSection').style.display = 'block';
        } else {
            currentUserId = null;
        }
    } catch(e) { console.error("Erro na sessão"); }
}

async function atualizarFoto() {
    const file = document.getElementById('changePicInput').files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('foto', file);
    const res = await fetch(`${API_URL}?action=atualizar_foto`, { method: 'POST', body: formData });
    const data = await res.json();
    if(data.sucesso) { checkSession(); loadFeed(); }
}

async function login() {
    const email = document.getElementById('loginEmail').value.trim();
    const senha = document.getElementById('loginSenha').value.trim();
    if(!email || !senha) return alert("Preencha tudo!");

    const formData = new FormData();
    formData.append('email', email);
    formData.append('senha', senha);
    
    const res = await fetch(`${API_URL}?action=login`, { method: 'POST', body: formData });
    const data = await res.json();
    
    if(data.sucesso) {
        closeModal('loginModal');
        checkSession();
        loadFeed();
    } else {
        document.getElementById('loginError').innerText = data.msg;
    }
}

async function register() {
    const nome = document.getElementById('regNome').value.trim();
    const username = document.getElementById('regUser').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const senha = document.getElementById('regSenha').value.trim();

    if (!nome || !username || !email || !senha) return alert("Preencha tudo!");

    const formData = new FormData();
    formData.append('nome', nome);
    formData.append('username', username);
    formData.append('email', email);
    formData.append('senha', senha);
    
    const res = await fetch(`${API_URL}?action=cadastrar`, { method: 'POST', body: formData });
    const data = await res.json();
    alert(data.msg || data.erro);
    
    if(data.sucesso) {
        closeModal('registerModal');
        document.getElementById('loginEmail').value = email;
        openModal('loginModal');
    }
}

async function logout() {
    await fetch(`${API_URL}?action=logout`);
    location.reload();
}

async function loadFeed(query = '') {
    const res = await fetch(query ? `${API_URL}?action=feed&q=${query}` : `${API_URL}?action=feed`);
    const posts = await res.json();
    let html = '';
    
    if(!posts.length) html = '<div class="card" style="text-align:center;">Nenhuma publicação.</div>';
    else {
        posts.forEach(p => {
            const likedClass = p.user_curtiu > 0 ? 'liked' : '';
            html += `
            <div class="card">
                <div style="display: flex; justify-content: space-between;">
                    <div style="display:flex; gap:10px;">
                        <img src="../backend/uploads/${p.foto}" onerror="this.src='../backend/uploads/avatar.png'" class="avatar"> 
                        <div>
                            <strong>${p.nome}</strong> <span style="color:gray;">@${p.username}</span>
                        </div>
                    </div>
                    ${p.id_usuario == currentUserId ? `<button class="btn-danger" onclick="deletePost(${p.id_publicacao})">Excluir</button>` : ''}
                </div>
                <p>${p.texto}</p>
                ${p.imagem ? `<img src="../backend/uploads/${p.imagem}" style="max-width:100%; border-radius:8px;">` : ''}
                
                <div style="display:flex; gap:15px; margin-top:15px; border-top:1px solid #eee; padding-top:10px;">
                    <span class="actions ${likedClass}" onclick="toggleLike(${p.id_publicacao})">❤️ ${p.curtidas}</span>
                    <span class="actions" onclick="toggleComments(${p.id_publicacao})">💬 Comentar</span>
                </div>

                <div id="comments-${p.id_publicacao}" style="display: none; margin-top: 15px;">
                    <div id="comments-list-${p.id_publicacao}"></div>
                    <div style="display: flex; gap: 5px; margin-top: 10px;">
                        <input type="text" id="comment-input-${p.id_publicacao}" placeholder="Comentar..." style="margin:0;">
                        <button onclick="addComment(${p.id_publicacao})" style="width:auto;">Enviar</button>
                    </div>
                </div>
            </div>`;
        });
    }
    document.getElementById('feed').innerHTML = html;
}

function searchUsers() { loadFeed(document.getElementById('searchInput').value); }

async function publishPost() {
    if(!currentUserId) return alert("Inicie sessão!");
    const texto = document.getElementById('postText').value.trim();
    if(!texto) return;

    const formData = new FormData();
    formData.append('texto', texto);
    if(document.getElementById('postImage').files[0]) formData.append('imagem', document.getElementById('postImage').files[0]);
    
    await fetch(`${API_URL}?action=publicar`, { method: 'POST', body: formData });
    document.getElementById('postText').value = '';
    document.getElementById('postImage').value = '';
    loadFeed();
}

async function toggleLike(id) {
    if(!currentUserId) return alert("Inicie sessão!");
    await fetch(`${API_URL}?action=curtir`, { method: 'POST', body: JSON.stringify({ id_publicacao: id }) });
    loadFeed();
}

async function deletePost(id) {
    if(confirm("Excluir?")) {
        await fetch(`${API_URL}?action=excluir`, { method: 'POST', body: JSON.stringify({ id_publicacao: id }) });
        loadFeed();
    }
}

async function toggleComments(id) {
    const el = document.getElementById(`comments-${id}`);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
    if(el.style.display === 'block') loadComments(id);
}

async function loadComments(id) {
    const res = await fetch(`${API_URL}?action=listar_comentarios&id_publicacao=${id}`);
    const comments = await res.json();
    let html = comments.length ? '' : '<small style="color:gray;">Sem comentários.</small>';
    
    comments.forEach(c => {
        html += `<div style="padding: 5px 0; border-bottom: 1px solid #eee;">
            <strong>${c.nome}:</strong> ${c.texto_comentario}
        </div>`;
    });
    
    document.getElementById(`comments-list-${id}`).innerHTML = html;
}

async function addComment(id) {
    if(!currentUserId) return;
    const input = document.getElementById(`comment-input-${id}`);
    if(!input.value.trim()) return;
    
    await fetch(`${API_URL}?action=comentar`, { method: 'POST', body: JSON.stringify({ id_publicacao: id, texto: input.value }) });
    input.value = '';
    loadComments(id);
}