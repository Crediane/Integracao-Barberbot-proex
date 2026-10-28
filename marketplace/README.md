# Marketplace Bot — Implantação de APIs

Projeto acadêmico desenvolvido em grupo para implantação e integração de APIs externas em um sistema PHP multiempresa.

## Objetivo

Cada integrante do grupo ficará responsável por uma integração:

- Mercado Pago;
- WhatsApp;
- Google Ads;
- Meta Ads.

O projeto-base foi preparado para desenvolvimento local com XAMPP e versionamento pelo GitHub.

## Repositório

```text
https://github.com/jcordeiro1/marketplace-bot
```

## Tecnologias

- PHP;
- MySQL;
- Apache;
- XAMPP;
- HTML, CSS e JavaScript;
- Git e GitHub.

## Requisitos

Antes de iniciar, instale:

- XAMPP;
- Git;
- Visual Studio Code ou outro editor;
- Composer, quando necessário;
- Conta no GitHub com acesso ao repositório.

No XAMPP, inicie:

- Apache;
- MySQL.

## Clonar o projeto

Abra o PowerShell em:

```text
C:\xampp\htdocs
```

Execute:

```powershell
git clone https://github.com/jcordeiro1/marketplace-bot.git
cd marketplace-bot
git status
```

O endereço local será:

```text
http://localhost/marketplace-bot/
```

## Banco de dados

1. Acesse:

```text
http://localhost/phpmyadmin
```

2. Crie o banco:

```text
marketplace
```

3. Utilize, quando disponível:

```text
utf8mb4_unicode_ci
```

4. Importe o arquivo SQL acadêmico disponível na pasta:

```text
database/
```

O banco disponibilizado para o trabalho deve conter somente a estrutura necessária, sem dados privados, senhas, tokens ou registros de produção.

## Configuração local

O arquivo principal de configuração é:

```text
config/marketplace.php
```

No XAMPP, a configuração normalmente utiliza:

```php
'host' => 'localhost',
'db'   => 'marketplace',
'user' => 'root',
'pass' => '',
```

Altere somente os valores necessários para o ambiente local.

Não altere a lógica central de conexão entre:

- MASTER;
- TENANT;
- MARKETPLACE.

Também não altere a resolução de loja por slug, domínio ou sessão sem aprovação do grupo.

## Teste inicial

Antes de implantar qualquer API, confirme que o sistema-base abre corretamente:

```text
http://localhost/marketplace-bot/
http://localhost/marketplace-bot/marketplace.php
```

Em caso de erro, verifique:

- Apache e MySQL iniciados;
- banco criado;
- SQL importado;
- nome do banco correto;
- usuário `root`;
- senha local;
- arquivo `config/marketplace.php`.

## Organização das branches

Nenhum integrante deve programar diretamente na branch `main`.

Antes de começar:

```powershell
git checkout main
git pull origin main
```

Crie a branch correspondente à sua tarefa.

### Mercado Pago

```powershell
git checkout -b feature/mercado-pago
```

### WhatsApp

```powershell
git checkout -b feature/whatsapp
```

### Google Ads

```powershell
git checkout -b feature/google-ads
```

### Meta Ads

```powershell
git checkout -b feature/meta-ads
```

Confirme a branch atual:

```powershell
git branch
```

## Responsabilidades

### Mercado Pago

O integrante responsável deverá implementar:

- configuração das credenciais;
- criação de pagamento;
- PIX, quando aplicável;
- consulta de status;
- retorno do pagamento;
- webhook;
- validação da notificação;
- atualização do status no banco;
- tratamento de erros;
- ambiente de testes.

Pastas que devem ser analisadas:

```text
pagamentos/
webhook/
api/
config/
```

Variáveis sugeridas:

```env
MERCADO_PAGO_ACCESS_TOKEN=
MERCADO_PAGO_PUBLIC_KEY=
MERCADO_PAGO_WEBHOOK_SECRET=
```

### WhatsApp

O integrante responsável deverá implementar:

- configuração da API;
- token ou chave de acesso;
- instância;
- envio de mensagem;
- confirmação de agendamento;
- lembrete;
- registro da resposta;
- tratamento de falhas;
- webhook, quando necessário.

Pastas que devem ser analisadas:

```text
api/
ajax/
webhook/
cron/
config/
```

Variáveis sugeridas:

```env
WHATSAPP_API_URL=
WHATSAPP_API_TOKEN=
WHATSAPP_INSTANCE_ID=
WHATSAPP_NUMBER=
```

### Google Ads

O integrante responsável deverá implementar pelo menos um fluxo completo, como registro de conversão ou consulta de campanhas.

Itens possíveis:

- OAuth;
- Developer Token;
- Client ID;
- Client Secret;
- Refresh Token;
- Customer ID;
- Login Customer ID;
- envio de conversões;
- consulta de campanhas;
- consulta de métricas;
- tratamento de erros.

Variáveis sugeridas:

```env
GOOGLE_ADS_DEVELOPER_TOKEN=
GOOGLE_ADS_CLIENT_ID=
GOOGLE_ADS_CLIENT_SECRET=
GOOGLE_ADS_REFRESH_TOKEN=
GOOGLE_ADS_CUSTOMER_ID=
GOOGLE_ADS_LOGIN_CUSTOMER_ID=
```

### Meta Ads

O integrante responsável deverá implementar a Meta Marketing API ou a Conversions API.

Itens possíveis:

- Access Token;
- App ID;
- App Secret;
- Pixel ID;
- Business ID;
- Ad Account ID;
- Page ID;
- envio de eventos;
- consulta de campanhas;
- consulta de métricas;
- tratamento de erros.

Variáveis sugeridas:

```env
META_APP_ID=
META_APP_SECRET=
META_ACCESS_TOKEN=
META_PIXEL_ID=
META_BUSINESS_ID=
META_AD_ACCOUNT_ID=
META_PAGE_ID=
```

Eventos possíveis:

```text
Lead
Schedule
Contact
Purchase
CompleteRegistration
```

## Segurança

É proibido enviar ao GitHub:

- Access Token;
- API Key;
- Client Secret;
- App Secret;
- Refresh Token;
- senha de banco;
- chave privada;
- token de webhook;
- credencial de produção;
- arquivos `credentials.json`;
- arquivos `service-account.json`;
- arquivos `.env` reais.

Nunca faça:

```php
$token = 'TOKEN_REAL_AQUI';
```

Use variáveis de ambiente:

```php
$token = getenv('MERCADO_PAGO_ACCESS_TOKEN') ?: '';
```

O repositório pode possuir somente um arquivo de exemplo:

```text
.env.example
```

Exemplo:

```env
MERCADO_PAGO_ACCESS_TOKEN=
WHATSAPP_API_TOKEN=
GOOGLE_ADS_CLIENT_SECRET=
META_ACCESS_TOKEN=
```

## Validar antes do commit

Verifique os arquivos alterados:

```powershell
git status
git diff
```

Valide a sintaxe PHP:

```powershell
php -l caminho\do\arquivo.php
```

Exemplo:

```powershell
php -l pagamentos\mercadopago.php
```

Resultado esperado:

```text
No syntax errors detected
```

Também teste o fluxo no navegador e verifique erros no PHP e no console.

## Criar commits

Depois de testar:

```powershell
git add .
git status
```

Crie uma mensagem clara:

```powershell
git commit -m "feat: adiciona criação de pagamento no Mercado Pago"
```

Outros exemplos:

```powershell
git commit -m "feat: implementa envio de confirmação pelo WhatsApp"
git commit -m "feat: registra conversões no Google Ads"
git commit -m "feat: envia eventos para a Meta Conversions API"
```

Evite mensagens genéricas como:

```text
alteração
teste
novo
ajuste
commit
```

## Enviar a branch

### Mercado Pago

```powershell
git push -u origin feature/mercado-pago
```

### WhatsApp

```powershell
git push -u origin feature/whatsapp
```

### Google Ads

```powershell
git push -u origin feature/google-ads
```

### Meta Ads

```powershell
git push -u origin feature/meta-ads
```

Depois do primeiro envio:

```powershell
git push
```

## Pull Request

No GitHub:

1. Abra o repositório;
2. clique em **Pull requests**;
3. clique em **New pull request**;
4. selecione a branch do aluno;
5. compare com a branch `main`;
6. informe o título e a descrição;
7. clique em **Create pull request**;
8. solicite a revisão de outro integrante.

A descrição deve informar:

- o que foi implementado;
- arquivos criados;
- arquivos alterados;
- como configurar;
- como testar;
- variáveis necessárias;
- erros tratados;
- evidências do funcionamento.

Nenhuma branch deve ser integrada à `main` sem revisão.

## Documentação

Cada integrante deverá criar um arquivo na pasta `docs`:

```text
docs/MERCADO_PAGO.md
docs/WHATSAPP.md
docs/GOOGLE_ADS.md
docs/META_ADS.md
```

A documentação deverá conter:

- nome e objetivo da API;
- arquivos criados;
- arquivos alterados;
- variáveis de ambiente;
- passo a passo da configuração;
- endpoint utilizado;
- formato da requisição;
- formato da resposta;
- tratamento de erros;
- como testar;
- prints ou evidências;
- limitações encontradas.

Não coloque tokens reais, senhas ou dados pessoais na documentação.

## Atualizar a branch durante o trabalho

```powershell
git checkout main
git pull origin main
git checkout nome-da-sua-branch
git merge main
```

Depois de trabalhar:

```powershell
git status
git diff
git add .
git commit -m "feat: descreva claramente o que foi implementado"
git push
```

## Checklist individual

- [ ] Branch própria criada;
- [ ] integração concluída;
- [ ] credenciais fora do GitHub;
- [ ] tratamento de erros implementado;
- [ ] dados validados;
- [ ] sintaxe PHP validada;
- [ ] teste funcional realizado;
- [ ] documentação criada;
- [ ] Pull Request aberto;
- [ ] apresentação preparada.

## Checklist do grupo

- [ ] Sistema funcionando no XAMPP;
- [ ] banco de dados importado;
- [ ] Mercado Pago integrado;
- [ ] WhatsApp integrado;
- [ ] Google Ads integrado;
- [ ] Meta Ads integrado;
- [ ] branches individuais disponíveis;
- [ ] Pull Requests revisados;
- [ ] documentação completa;
- [ ] histórico de commits organizado.

## Regra final

Nunca programar diretamente na `main`, nunca enviar credenciais ao GitHub e nunca alterar a lógica central de conexão sem aprovação do grupo.
