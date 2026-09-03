<?php
require_once __DIR__ . '/marketplace/header.php';

if (!function_exists('bb_legal_h')) {
    function bb_legal_h($valor) {
        return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
    }
}

$bb_nome_site = $nome_sistema ?? 'BarberBot';
$bb_email_contato = trim((string)($email_sistema ?? ''));
$bb_whatsapp_contato = trim((string)($whatsapp_sistema ?? ''));
$bb_url_site = '';

$bb_proto_legal = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $bb_xfp_legal = strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']);
    if ($bb_xfp_legal === 'https' || $bb_xfp_legal === 'http') {
        $bb_proto_legal = $bb_xfp_legal;
    }
}
if (!empty($_SERVER['HTTP_HOST'])) {
    $bb_url_site = $bb_proto_legal . '://' . $_SERVER['HTTP_HOST'];
}
if ($bb_email_contato === '') {
    $bb_email_contato = 'contato@jc.tec.br';
}
?>

<style>
.bb-legal-page{
    padding:130px 5% 70px;
    background:
        radial-gradient(circle at top right, rgba(37,99,235,.18), transparent 35%),
        linear-gradient(180deg,#020617 0%,#0b1120 100%);
    min-height:100vh;
}
.bb-legal-wrap{
    max-width:1120px;
    margin:0 auto;
}
.bb-legal-card{
    background:rgba(15,23,42,.92);
    border:1px solid rgba(148,163,184,.18);
    border-radius:24px;
    box-shadow:0 24px 70px rgba(0,0,0,.35);
    overflow:hidden;
}
.bb-legal-hero{
    padding:42px 38px;
    border-bottom:1px solid rgba(148,163,184,.18);
    background:
        radial-gradient(circle at 20% 10%, rgba(59,130,246,.22), transparent 35%),
        linear-gradient(135deg,rgba(37,99,235,.22),rgba(2,6,23,.35));
}
.bb-legal-kicker{
    color:#60a5fa;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.08em;
    font-size:.82rem;
    margin-bottom:10px;
}
.bb-legal-title{
    color:#f8fafc;
    font-size:clamp(2rem,4vw,3.2rem);
    line-height:1.1;
    margin:0 0 14px;
    font-weight:900;
}
.bb-legal-desc{
    color:#cbd5e1;
    font-size:1.05rem;
    line-height:1.8;
    max-width:860px;
    margin:0;
}
.bb-legal-body{
    padding:38px;
    color:#cbd5e1;
    font-size:1rem;
    line-height:1.8;
}
.bb-legal-body h2{
    color:#f8fafc;
    font-size:1.45rem;
    margin:30px 0 12px;
}
.bb-legal-body h2:first-child{
    margin-top:0;
}
.bb-legal-body h3{
    color:#dbeafe;
    font-size:1.08rem;
    margin:20px 0 8px;
}
.bb-legal-body p{
    margin:0 0 14px;
}
.bb-legal-body ul{
    list-style:none;
    padding:0;
    margin:0 0 18px;
}
.bb-legal-body li{
    position:relative;
    padding-left:28px;
    margin:8px 0;
}
.bb-legal-body li:before{
    content:"";
    width:9px;
    height:9px;
    border-radius:50%;
    background:#2563eb;
    position:absolute;
    left:4px;
    top:.72em;
}
.bb-legal-box{
    background:rgba(2,6,23,.48);
    border:1px solid rgba(96,165,250,.22);
    border-radius:18px;
    padding:18px;
    margin:22px 0;
}
.bb-legal-table{
    width:100%;
    border-collapse:collapse;
    margin:18px 0;
    overflow:hidden;
    border-radius:16px;
}
.bb-legal-table th,
.bb-legal-table td{
    border:1px solid rgba(148,163,184,.18);
    padding:13px 14px;
    vertical-align:top;
}
.bb-legal-table th{
    background:rgba(37,99,235,.18);
    color:#f8fafc;
    text-align:left;
}
.bb-legal-link{
    color:#93c5fd;
    text-decoration:underline;
    text-underline-offset:3px;
}
.bb-legal-actions{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    margin-top:26px;
}
.bb-legal-actions a{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:12px 22px;
    border-radius:999px;
    font-weight:800;
    text-decoration:none;
}
.bb-legal-actions .primary{
    background:#2563eb;
    color:#fff;
}
.bb-legal-actions .outline{
    border:1px solid rgba(248,250,252,.35);
    color:#f8fafc;
}
@media(max-width:768px){
    .bb-legal-page{padding:105px 16px 45px;}
    .bb-legal-hero,.bb-legal-body{padding:26px 20px;}
    .bb-legal-table{display:block;overflow-x:auto;}
}
</style>

<main class="bb-legal-page">
    <div class="bb-legal-wrap">
        <article class="bb-legal-card">
            <header class="bb-legal-hero">
                <div class="bb-legal-kicker">Transparência e proteção de dados</div>
                <h1 class="bb-legal-title">Política de Privacidade</h1>
                <p class="bb-legal-desc">
                    Esta Política explica como o <?php echo bb_legal_h($bb_nome_site); ?> coleta, utiliza, armazena e protege dados pessoais
                    relacionados ao site, marketplace, páginas públicas, formulários, anúncios, atendimento, integrações e uso da plataforma.
                </p>
            </header>

            <section class="bb-legal-body">
                <div class="bb-legal-box">
                    <p><strong>Última atualização:</strong> 26 de maio de 2026.</p>
                    <p>
                        Esta página foi preparada para deixar claras as práticas de privacidade do site e apoiar a conformidade com campanhas de mídia paga,
                        incluindo Google Ads, Google Tag Manager, Google Analytics, pixels de conversão e tecnologias semelhantes.
                    </p>
                </div>

                <h2>1. Quem somos</h2>
                <p>
                    O <?php echo bb_legal_h($bb_nome_site); ?> é uma plataforma de gestão, agendamento online, automação de atendimento e recursos digitais
                    voltados para barbearias, salões de beleza, profissionais da área da beleza e estabelecimentos que utilizam agenda online.
                </p>
                <p>
                    Site principal: <strong><?php echo bb_legal_h($bb_url_site !== '' ? $bb_url_site : 'https://jc.tec.br'); ?></strong>.
                    Canal de contato: <strong><?php echo bb_legal_h($bb_email_contato); ?></strong>
                    <?php if ($bb_whatsapp_contato !== ''): ?>
                        ou WhatsApp <strong><?php echo bb_legal_h($bb_whatsapp_contato); ?></strong>.
                    <?php endif; ?>
                </p>

                <h2>2. Dados que podemos coletar</h2>
                <p>Podemos coletar dados conforme a interação do usuário com o site, páginas públicas, formulários e sistema:</p>
                <ul>
                    <li>Nome, telefone, e-mail e dados enviados em formulários de contato, cadastro ou solicitação de avaliação.</li>
                    <li>Dados do estabelecimento, como nome comercial, endereço, serviços, profissionais, horários, agenda e informações públicas da loja.</li>
                    <li>Dados de agendamento, como serviço escolhido, data, horário, profissional, status do atendimento e histórico operacional.</li>
                    <li>Dados técnicos, como endereço IP, navegador, dispositivo, sistema operacional, páginas acessadas, data, horário e origem do acesso.</li>
                    <li>Dados de marketing, como origem da campanha, parâmetros UTM, cliques, conversões, eventos de navegação e preferências de cookies.</li>
                    <li>Dados de pagamento ou cobrança quando houver contratação de planos, emissão de recibos, integração com gateways ou controle financeiro.</li>
                    <li>Mensagens e informações necessárias para suporte, atendimento, recuperação de acesso e comunicação com o cliente.</li>
                </ul>

                <h2>3. Finalidades de uso dos dados</h2>
                <p>Os dados podem ser utilizados para:</p>
                <ul>
                    <li>Operar o site, marketplace, agenda online, área administrativa e funcionalidades contratadas.</li>
                    <li>Processar cadastros, solicitações, testes gratuitos, assinaturas, planos e atendimento comercial.</li>
                    <li>Enviar comunicações operacionais, confirmações, lembretes, avisos e mensagens relacionadas ao serviço.</li>
                    <li>Melhorar segurança, prevenir fraudes, proteger contas e manter registros técnicos necessários.</li>
                    <li>Medir desempenho de campanhas, páginas, anúncios, conversões e navegação.</li>
                    <li>Personalizar comunicações e anúncios quando permitido pela legislação e pelas preferências do usuário.</li>
                    <li>Cumprir obrigações legais, regulatórias, fiscais, contratuais e solicitações de autoridades competentes.</li>
                </ul>

                <h2>4. Cookies, Google Ads e ferramentas de medição</h2>
                <p>
                    O site pode utilizar cookies e tecnologias semelhantes para funcionamento, segurança, análise de desempenho, mensuração de conversões
                    e marketing. Essas tecnologias podem ser próprias ou de terceiros.
                </p>

                <table class="bb-legal-table">
                    <thead>
                        <tr>
                            <th>Tipo de cookie/tecnologia</th>
                            <th>Finalidade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Necessários</td>
                            <td>Permitem navegação, segurança, sessão, preferências essenciais e funcionamento básico do site.</td>
                        </tr>
                        <tr>
                            <td>Análise e desempenho</td>
                            <td>Ajudam a entender visitas, páginas acessadas, erros, origem do tráfego e melhoria da experiência.</td>
                        </tr>
                        <tr>
                            <td>Marketing e conversão</td>
                            <td>Podem medir campanhas, registrar conversões, criar públicos, limitar frequência e melhorar a relevância dos anúncios.</td>
                        </tr>
                    </tbody>
                </table>

                <p>
                    Ferramentas como Google Ads, Google Tag Manager, Google Analytics, reCAPTCHA, Meta Ads ou tecnologias semelhantes podem coletar
                    informações técnicas e eventos de navegação conforme suas próprias políticas. O usuário pode gerenciar preferências de anúncios
                    do Google em
                    <a class="bb-legal-link" href="https://adssettings.google.com/" target="_blank" rel="noopener noreferrer">adssettings.google.com</a>
                    e consultar a política do Google em
                    <a class="bb-legal-link" href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">policies.google.com/privacy</a>.
                </p>

                <h2>5. Compartilhamento de dados</h2>
                <p>Podemos compartilhar dados somente quando necessário para as finalidades descritas nesta Política, incluindo:</p>
                <ul>
                    <li>Provedores de hospedagem, infraestrutura, banco de dados, segurança, e-mail, APIs e suporte técnico.</li>
                    <li>Ferramentas de análise, anúncios, tags, pixels, medição de conversão e prevenção de abuso.</li>
                    <li>Gateways de pagamento, instituições financeiras, intermediadores e serviços de cobrança quando houver contratação.</li>
                    <li>Serviços de comunicação, incluindo WhatsApp, SMS, e-mail ou integrações usadas para envio de mensagens autorizadas.</li>
                    <li>Autoridades públicas, órgãos reguladores ou terceiros quando houver obrigação legal, ordem judicial ou necessidade de proteção de direitos.</li>
                </ul>
                <p>
                    Não vendemos dados pessoais. O compartilhamento ocorre de forma limitada, proporcional e vinculada ao funcionamento do serviço,
                    segurança, atendimento, medição ou obrigação legal.
                </p>

                <h2>6. Base legal e LGPD</h2>
                <p>
                    O tratamento de dados poderá ocorrer com base em execução de contrato, cumprimento de obrigação legal, legítimo interesse,
                    consentimento, exercício regular de direitos, proteção ao crédito, prevenção a fraudes e demais hipóteses previstas na Lei Geral
                    de Proteção de Dados Pessoais.
                </p>

                <h2>7. Retenção dos dados</h2>
                <p>
                    Os dados serão mantidos pelo tempo necessário para cumprir as finalidades informadas, obrigações legais, registros de segurança,
                    suporte, auditoria, cobrança, prevenção de fraude e exercício de direitos. Quando não houver necessidade de manutenção, os dados
                    poderão ser excluídos, anonimizados ou bloqueados conforme aplicável.
                </p>

                <h2>8. Segurança</h2>
                <p>
                    Utilizamos medidas técnicas e administrativas para proteger informações contra acessos não autorizados, perda, alteração,
                    divulgação indevida ou destruição. Ainda assim, nenhum ambiente digital é totalmente livre de riscos, e o usuário também deve
                    proteger suas credenciais, dispositivos e acessos.
                </p>

                <h2>9. Direitos do titular</h2>
                <p>Nos termos da LGPD, você pode solicitar:</p>
                <ul>
                    <li>Confirmação da existência de tratamento de dados.</li>
                    <li>Acesso, correção, atualização ou complementação dos dados.</li>
                    <li>Anonimização, bloqueio ou eliminação de dados desnecessários, excessivos ou tratados em desconformidade.</li>
                    <li>Portabilidade, quando aplicável e conforme regulamentação.</li>
                    <li>Informações sobre compartilhamento de dados.</li>
                    <li>Revogação do consentimento e oposição ao tratamento, quando cabível.</li>
                </ul>
                <p>
                    Para exercer seus direitos, entre em contato pelo e-mail <strong><?php echo bb_legal_h($bb_email_contato); ?></strong>.
                </p>

                <h2>10. Crianças e adolescentes</h2>
                <p>
                    O site e a plataforma são direcionados a responsáveis por negócios, profissionais, estabelecimentos e clientes que utilizam
                    agenda online. Não buscamos coletar intencionalmente dados de crianças. Caso algum dado seja identificado como indevido,
                    o responsável poderá solicitar a remoção pelo canal de contato informado nesta Política.
                </p>

                <h2>11. Links externos</h2>
                <p>
                    O site pode conter links para páginas externas, como Google, WhatsApp, redes sociais, gateways de pagamento, perfis de empresas
                    e sites de terceiros. Não nos responsabilizamos pelas práticas de privacidade desses ambientes externos.
                </p>

                <h2>12. Atualizações desta Política</h2>
                <p>
                    Esta Política poderá ser atualizada para refletir mudanças legais, técnicas, operacionais, comerciais ou de segurança.
                    A versão válida será sempre a publicada nesta página.
                </p>

                <div class="bb-legal-actions">
                    <a class="primary" href="/termos-uso.php"><i class="fa-solid fa-file-contract"></i> Ver Termos de Uso</a>
                    <a class="outline" href="/"><i class="fa-solid fa-house"></i> Voltar para o início</a>
                </div>
            </section>
        </article>
    </div>
</main>

<?php
require_once __DIR__ . '/marketplace/footer.php';
?>
