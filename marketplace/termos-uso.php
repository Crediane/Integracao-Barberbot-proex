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
}
</style>

<main class="bb-legal-page">
    <div class="bb-legal-wrap">
        <article class="bb-legal-card">
            <header class="bb-legal-hero">
                <div class="bb-legal-kicker">Condições de contratação e uso</div>
                <h1 class="bb-legal-title">Termos de Uso</h1>
                <p class="bb-legal-desc">
                    Estes Termos regulam o acesso e uso do <?php echo bb_legal_h($bb_nome_site); ?>, incluindo site, marketplace, páginas públicas,
                    agenda online, recursos de automação, painel administrativo, integrações e serviços relacionados.
                </p>
            </header>

            <section class="bb-legal-body">
                <div class="bb-legal-box">
                    <p><strong>Última atualização:</strong> 26 de maio de 2026.</p>
                    <p>
                        Ao acessar o site, criar conta, solicitar avaliação, testar a plataforma, contratar plano ou utilizar qualquer funcionalidade,
                        você declara que leu, entendeu e concorda com estes Termos de Uso.
                    </p>
                </div>

                <h2>1. Sobre o serviço</h2>
                <p>
                    O <?php echo bb_legal_h($bb_nome_site); ?> oferece recursos digitais para gestão, agendamento online, organização financeira,
                    atendimento, automação de lembretes, campanhas, páginas públicas e ferramentas de apoio para barbearias, salões de beleza,
                    profissionais e estabelecimentos.
                </p>
                <p>
                    Site principal: <strong><?php echo bb_legal_h($bb_url_site !== '' ? $bb_url_site : 'https://jc.tec.br'); ?></strong>.
                    Contato: <strong><?php echo bb_legal_h($bb_email_contato); ?></strong>
                    <?php if ($bb_whatsapp_contato !== ''): ?>
                        ou WhatsApp <strong><?php echo bb_legal_h($bb_whatsapp_contato); ?></strong>.
                    <?php endif; ?>
                </p>

                <h2>2. Cadastro e responsabilidade do usuário</h2>
                <p>O usuário é responsável por:</p>
                <ul>
                    <li>Informar dados verdadeiros, completos e atualizados.</li>
                    <li>Manter sigilo de login, senha e acessos administrativos.</li>
                    <li>Usar o sistema de forma lícita, ética e compatível com estes Termos.</li>
                    <li>Ter autorização para cadastrar dados de clientes, profissionais, serviços, imagens, preços e informações do estabelecimento.</li>
                    <li>Conferir agenda, cobranças, integrações, mensagens e informações antes de enviar ou publicar.</li>
                </ul>

                <h2>3. Planos, teste gratuito e pagamentos</h2>
                <p>
                    Os planos, valores, períodos de teste, recursos disponíveis e condições comerciais são exibidos no site ou informados no momento
                    da contratação. Os recursos podem variar conforme plano, configuração, integrações contratadas e disponibilidade técnica.
                </p>
                <ul>
                    <li>Períodos gratuitos, quando oferecidos, podem ter limitações de uso, prazo, recursos ou integrações.</li>
                    <li>A continuidade do serviço após o período gratuito pode depender de contratação e pagamento do plano escolhido.</li>
                    <li>Valores, promoções e condições podem ser alterados, respeitando contratações vigentes e comunicações aplicáveis.</li>
                    <li>Falhas de pagamento, chargebacks, suspeita de fraude ou inadimplência podem gerar bloqueio, suspensão ou cancelamento do acesso.</li>
                </ul>

                <h2>4. Integrações com terceiros</h2>
                <p>
                    O sistema pode integrar ou depender de serviços de terceiros, como WhatsApp, gateways de pagamento, Google, Meta, ferramentas de
                    análise, APIs, hospedagem, envio de mensagens e outros provedores. Essas integrações podem possuir regras, limites, instabilidades,
                    custos, políticas e prazos próprios.
                </p>
                <p>
                    O <?php echo bb_legal_h($bb_nome_site); ?> não controla decisões, bloqueios, políticas, indisponibilidades ou alterações feitas
                    por plataformas externas.
                </p>

                <h2>5. Uso de WhatsApp, mensagens e campanhas</h2>
                <p>
                    O usuário deve utilizar mensagens, lembretes e campanhas respeitando autorização dos contatos, leis aplicáveis, boas práticas,
                    políticas das plataformas e regras contra spam. É proibido enviar conteúdo ilícito, enganoso, abusivo, discriminatório,
                    invasivo ou sem autorização.
                </p>

                <h2>6. Conteúdo publicado pelo usuário</h2>
                <p>
                    O usuário é responsável por textos, imagens, logotipos, avaliações, serviços, preços, nomes, páginas públicas, informações de
                    profissionais e qualquer conteúdo inserido ou publicado no sistema. Ao enviar conteúdo, o usuário declara possuir direito de uso
                    e autoriza a exibição dentro das funcionalidades contratadas.
                </p>

                <h2>7. Resultados, anúncios e promessas comerciais</h2>
                <p>
                    O <?php echo bb_legal_h($bb_nome_site); ?> fornece tecnologia para organização, automação, atendimento e gestão. A plataforma
                    não garante aumento específico de faturamento, quantidade de clientes, posição em buscadores, aprovação de anúncios, retorno
                    financeiro ou resultado comercial. Os resultados dependem de fatores como uso correto do sistema, mercado, equipe, atendimento,
                    estratégia, preço, demanda, concorrência e investimento em marketing.
                </p>

                <h2>8. Disponibilidade e manutenção</h2>
                <p>
                    Buscamos manter o serviço disponível e seguro, mas podem ocorrer interrupções por manutenção, atualizações, falhas técnicas,
                    instabilidade de terceiros, ataques, eventos de força maior ou situações fora do controle razoável. Sempre que possível, ajustes
                    serão feitos para reduzir impactos.
                </p>

                <h2>9. Proibições</h2>
                <p>Não é permitido:</p>
                <ul>
                    <li>Usar o sistema para fraude, spam, golpes, atividades ilegais ou violação de direitos de terceiros.</li>
                    <li>Tentar invadir, copiar, explorar falhas, engenharia reversa ou prejudicar a segurança da plataforma.</li>
                    <li>Inserir dados falsos, conteúdo ofensivo, enganoso, discriminatório ou que viole leis e políticas de terceiros.</li>
                    <li>Revender, sublicenciar ou disponibilizar acesso sem autorização formal, quando isso não fizer parte do plano contratado.</li>
                    <li>Utilizar automações de forma abusiva ou contrária às políticas das plataformas integradas.</li>
                </ul>

                <h2>10. Cancelamento e encerramento</h2>
                <p>
                    O usuário pode solicitar cancelamento conforme as condições do plano contratado. Também poderemos suspender ou encerrar o acesso
                    em caso de violação destes Termos, inadimplência, risco à segurança, suspeita de fraude, uso indevido ou exigência legal.
                </p>

                <h2>11. Propriedade intelectual</h2>
                <p>
                    Marcas, layout, código, textos, estrutura, recursos, identidade visual, funcionalidades, documentação e demais elementos do
                    <?php echo bb_legal_h($bb_nome_site); ?> pertencem aos seus respectivos titulares. O uso da plataforma não transfere propriedade
                    intelectual ao usuário.
                </p>

                <h2>12. Limitação de responsabilidade</h2>
                <p>
                    Na máxima extensão permitida pela lei, o <?php echo bb_legal_h($bb_nome_site); ?> não será responsável por perdas indiretas,
                    lucros cessantes, perda de dados causada por uso indevido, falhas de terceiros, decisões comerciais do usuário, campanhas externas,
                    bloqueios de plataformas integradas ou eventos fora de controle razoável.
                </p>

                <h2>13. Privacidade e proteção de dados</h2>
                <p>
                    O tratamento de dados pessoais está descrito na Política de Privacidade. Ao utilizar o serviço, o usuário também concorda com
                    as regras de privacidade, cookies e tratamento de dados aplicáveis.
                </p>

                <h2>14. Alterações destes Termos</h2>
                <p>
                    Estes Termos podem ser atualizados para refletir mudanças técnicas, comerciais, legais ou operacionais. A versão válida será
                    sempre a publicada nesta página.
                </p>

                <h2>15. Foro e legislação aplicável</h2>
                <p>
                    Estes Termos são regidos pelas leis brasileiras. Eventuais conflitos serão tratados preferencialmente por contato direto e,
                    quando necessário, pelo foro competente conforme a legislação aplicável.
                </p>

                <div class="bb-legal-actions">
                    <a class="primary" href="/politica-privacidade.php"><i class="fa-solid fa-lock"></i> Ver Política de Privacidade</a>
                    <a class="outline" href="/"><i class="fa-solid fa-house"></i> Voltar para o início</a>
                </div>
            </section>
        </article>
    </div>
</main>

<?php
require_once __DIR__ . '/marketplace/footer.php';
?>
