
# BotSystem - Painel Administrativo v1.0.0

Sistema completo para envio de mensagens, imagens, enquetes e recomendações automáticas de filmes/séries para WhatsApp e Telegram.

## 🚀 Características

- **Múltiplas Plataformas**: WhatsApp (EVO API v2) e Telegram
- **Conteúdo TMDB**: Filmes e séries populares com posters e detalhes
- **Agendamento**: Sistema de cronjob para envios automáticos
- **Enquetes**: Suporte a enquetes nativas do Telegram
- **Relatórios**: Histórico completo com filtros e exportação
- **Responsivo**: Interface moderna e mobile-first

## 📁 Estrutura do Projeto

```
BotSystem/
├── dashboard.php          # Dashboard principal
├── bots.php              # Gerenciar bots
├── enviar.php            # Envio manual de mensagens
├── enquete.php           # Criar enquetes (Telegram)
├── tmdb.php              # Filmes e séries do TMDB
├── agendar.php           # Agendar envios
├── relatorio.php         # Relatórios e estatísticas  
├── configuracoes.php     # Configurações do sistema
├── login.php             # Sistema de login
├── index.html            # Página inicial
├── includes/
│   ├── auth.php          # Autenticação
│   ├── header.php        # Cabeçalho
│   ├── sidebar.php       # Menu lateral
│   └── footer.php        # Rodapé
├── assets/
│   ├── style.css         # Estilos CSS
│   └── script.js         # Scripts JavaScript
├── data/                 # Arquivos JSON
├── uploads/              # Upload de imagens
└── cron/                 # Scripts para cronjob
```

## 🔧 Instalação

1. Faça upload de todos os arquivos para seu servidor web
2. Certifique-se que as pastas `data/` e `uploads/` tenham permissão de escrita (755)
3. Acesse o sistema pelo navegador
4. Login padrão: **admin** / **admin**

## ⚙️ Configuração

### APIs Necessárias

1. **TMDB API**: Registre-se em https://www.themoviedb.org/settings/api
2. **WhatsApp EVO API v2**: Configure sua instância
3. **Telegram Bot**: Crie um bot via @BotFather

### Configurar APIs

Acesse **Configurações** no painel e preencha:

```json
{
  "tmdb_key": "SUA_CHAVE_TMDB",
  "whatsapp": {
    "server": "https://evov2.duckdns.org", 
    "instance": "SEU_INSTANCE",
    "apikey": "SUA_API_KEY"
  }
}
```

### Cronjob para Agendamentos

Configure no cPanel ou servidor:

```bash
* * * * * php /caminho/para/BotSystem/cron/processar_agendamentos.php
```

## 📱 Funcionalidades

### Dashboard
- Status das APIs
- Estatísticas de envios
- Bots ativos
- Últimos envios

### Gerenciar Bots
- Cadastrar bots WhatsApp e Telegram
- Ativar/desativar bots
- Editar configurações

### Enviar Mensagens
- Texto simples
- Imagem com legenda
- Envio para números/grupos específicos

### TMDB - Filmes e Séries
- 🔥 Filmes populares da semana
- 📺 Séries em alta
- Cards com poster, sinopse, trailer
- Envio direto ou agendamento
- Etiquetas: 🆕 Lançamento, 🎬 Filme, 📺 Série

### Enquetes
- Enquetes nativas do Telegram
- Até 4 opções por enquete
- Dicas para "enquetes" no WhatsApp

### Agendamentos
- Agendar mensagens para data/hora específica
- Conteúdo TMDB automático
- Processamento via cronjob

### Relatórios
- Histórico completo de envios
- Filtros por tipo, status, data
- Estatísticas e gráficos
- Exportação CSV

## 🎨 Formato das Mensagens

### Filmes/Séries (TMDB)
```
🎬 Título do Filme

📝 Sinopse:
Descrição completa do filme...

⭐ Avaliação: 8.5/10 ⭐⭐⭐⭐⭐

🎭 Gêneros: Ação, Drama, Thriller

📅 Lançamento: 01/01/2024

🎥 Trailer: https://youtube.com/...

🤖 Sugestão automática via BotSystem
```

### WhatsApp - Formatos Suportados
- Número: `5521999999999@c.us`
- Grupo: `120363020010000987@g.us`

### Telegram - Formatos Suportados  
- Username: `@meucanal`
- Chat ID: `-1001234567890`

## 🔐 Segurança

- Autenticação por sessão
- Senhas criptografadas (password_hash)
- Diretório `/cron/` protegido via .htaccess
- Validação de formulários

## 🛠️ Personalização

### CSS
Edite `assets/style.css` para personalizar:
- Cores do tema
- Layout responsivo
- Componentes visuais

### JavaScript
Edite `assets/script.js` para adicionar:
- Validações customizadas
- Funcionalidades extras
- Integrações adicionais

## 📊 Armazenamento

Todos os dados são salvos em arquivos JSON:
- `usuarios.json` - Usuários do sistema
- `bots.json` - Bots cadastrados
- `logs.json` - Histórico de envios
- `agendamentos.json` - Mensagens agendadas
- `config.json` - Configurações das APIs

## 🐛 Solução de Problemas

### Erro de Permissão
```bash
chmod 755 data/
chmod 755 uploads/
```

### APIs não funcionam
1. Verifique as chaves nas configurações
2. Teste conectividade com os servidores
3. Confirme formato dos destinos

### Agendamentos não executam
1. Verifique se o cronjob está configurado
2. Confirme permissões do arquivo `processar_agendamentos.php`
3. Consulte logs do servidor

## 📞 Suporte

Para dúvidas ou problemas:
1. Verifique os logs em `data/cron.log`
2. Consulte a documentação das APIs
3. Teste as configurações no dashboard

---

**BotSystem v1.0.0** - Sistema desenvolvido para automatização de envios em WhatsApp e Telegram.
