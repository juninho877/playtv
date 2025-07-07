
// Toggle sidebar no mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.overlay');
    
    sidebar.classList.toggle('active');
    
    if (!overlay) {
        const newOverlay = document.createElement('div');
        newOverlay.className = 'overlay';
        newOverlay.onclick = toggleSidebar;
        document.body.appendChild(newOverlay);
    }
    
    document.querySelector('.overlay').classList.toggle('active');
}

// Fechar sidebar ao clicar em link no mobile
document.addEventListener('DOMContentLoaded', function() {
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                toggleSidebar();
            }
        });
    });
});

// Função para mostrar alertas
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    const container = document.querySelector('.main-content');
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Função para confirmar ações
function confirmarAcao(message) {
    return confirm(message);
}

// Função para copiar texto
function copiarTexto(texto) {
    navigator.clipboard.writeText(texto).then(() => {
        showAlert('Texto copiado para a área de transferência!', 'success');
    });
}

// Função para preview de imagem
function previewImage(input, previewId) {
    const file = input.files[0];
    const preview = document.getElementById(previewId);
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
}

// Função para formatear data
function formatarData(data) {
    return new Date(data).toLocaleString('pt-BR');
}

// Função para calcular tempo decorrido
function tempoDecorrido(data) {
    const agora = new Date();
    const passado = new Date(data);
    const diff = agora - passado;
    
    const minutos = Math.floor(diff / 60000);
    const horas = Math.floor(minutos / 60);
    const dias = Math.floor(horas / 24);
    
    if (dias > 0) return `${dias}d atrás`;
    if (horas > 0) return `${horas}h atrás`;
    if (minutos > 0) return `${minutos}min atrás`;
    return 'Agora mesmo';
}

// Função para validar formulários
function validarFormulario(formId) {
    const form = document.getElementById(formId);
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    for (let input of inputs) {
        if (!input.value.trim()) {
            showAlert(`Por favor, preencha o campo ${input.labels[0]?.textContent || input.placeholder}`, 'error');
            input.focus();
            return false;
        }
    }
    
    return true;
}

// Auto-resize textarea
document.addEventListener('DOMContentLoaded', function() {
    const textareas = document.querySelectorAll('textarea');
    
    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    });
});

// Função para filtrar tabelas
function filtrarTabela(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    const rows = table.querySelectorAll('tbody tr');
    
    input.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
}

// Função para carregar conteúdo via AJAX
function carregarConteudo(url, containerId, callback) {
    fetch(url)
        .then(response => response.text())
        .then(data => {
            document.getElementById(containerId).innerHTML = data;
            if (callback) callback();
        })
        .catch(error => {
            console.error('Erro:', error);
            showAlert('Erro ao carregar conteúdo', 'error');
        });
}

// Função para enviar formulário via AJAX
function enviarFormulario(formId, callback) {
    const form = document.getElementById(formId);
    const formData = new FormData(form);
    
    fetch(form.action || window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            if (callback) callback(data);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showAlert('Erro ao processar solicitação', 'error');
    });
}

// Função para atualizar status em tempo real
function atualizarStatus() {
    fetch('api/status.php')
        .then(response => response.json())
        .then(data => {
            // Atualizar indicadores de status
            const indicators = document.querySelectorAll('[data-status]');
            indicators.forEach(indicator => {
                const service = indicator.getAttribute('data-status');
                if (data[service]) {
                    indicator.innerHTML = data[service].status ? '✅' : '❌';
                    indicator.title = data[service].message || '';
                }
            });
        })
        .catch(error => console.error('Erro ao atualizar status:', error));
}

// Atualizar status a cada 30 segundos
setInterval(atualizarStatus, 30000);

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    atualizarStatus();
});
