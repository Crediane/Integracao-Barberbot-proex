<?php
require_once("sistema/conexao.php");

// ===== Rotas públicas do rodapé com suporte ao TENANT /loja/{slug}/ =====
// Mantém o MASTER fora de /loja/ e evita links relativos quebrando dentro de URLs dinâmicas.
if (!function_exists('bb_footer_clean_slug')) {
  function bb_footer_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return (string)preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

$bb_footer_req_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$bb_footer_tenant_slug = '';

if (!empty($_GET['loja'])) {
  $bb_footer_tenant_slug = bb_footer_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
  $bb_footer_tenant_slug = bb_footer_clean_slug($_POST['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $bb_footer_req_path, $bb_footer_match)) {
  $bb_footer_tenant_slug = bb_footer_clean_slug($bb_footer_match[1]);
} elseif (!empty($_SESSION['tenant_slug'])) {
  $bb_footer_tenant_slug = bb_footer_clean_slug($_SESSION['tenant_slug']);
}

$bb_footer_base = ($bb_footer_tenant_slug !== '') ? ('/loja/' . rawurlencode($bb_footer_tenant_slug) . '/') : '/';

if (!function_exists('bb_footer_url')) {
  function bb_footer_url($caminho = '') {
    global $bb_footer_base;
    $caminho = ltrim((string)$caminho, '/');
    return $caminho === '' ? $bb_footer_base : $bb_footer_base . $caminho;
  }
}

$bb_footer_url_home = bb_footer_url('');
$bb_footer_url_assinatura = bb_footer_url('assinatura');
$bb_footer_url_agendamentos = bb_footer_url('agendamentos');
$bb_footer_url_produtos = bb_footer_url('produtos');
$bb_footer_url_servicos = bb_footer_url('servicos');
$bb_footer_url_cadastrar = bb_footer_url('cadastrar.php');
$bb_footer_url_acesso_cliente = bb_footer_url('sistema/acesso');
$bb_footer_url_sistema = ($bb_footer_tenant_slug !== '') ? bb_footer_url('prof/') : '/sistema';
$bb_footer_url_politica = '/politica-privacidade.php';
$bb_footer_url_termos = '/termos-uso.php';
$bb_footer_hidden_loja = ($bb_footer_tenant_slug !== '') ? '<input type="hidden" name="loja" value="' . htmlspecialchars($bb_footer_tenant_slug, ENT_QUOTES, 'UTF-8') . '">' : '';
?>

<!-- CSS para Modal por Cima do Menu -->
<style>
.modal-backdrop.show { z-index: 9998 !important; }
#leadModal { z-index: 9999 !important; }
</style>

<footer class="footer_section" style="background:#020202;width:100%;margin-top:0;padding-top:1px;border-top:0;">
  <div class="container">
    <div class="footer_content">
      <div class="row">
        <div class="col-md-5 col-lg-5 footer-col">
          <div class="footer_detail">
            <h4>
              <a href="<?php echo htmlspecialchars($bb_footer_url_home, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Ir para a página inicial">
                <img
                  src="/sistema/img/logo.png"
                  width="100"
                  alt="<?php echo htmlspecialchars($nome_sistema, ENT_QUOTES, 'UTF-8'); ?>"
                  decoding="async"
                  style="margin-right:5px;"
                >
                <span style="color:#fff;"><?php echo $nome_sistema; ?></span>
              </a>
            </h4>
            <p><?php echo $texto_rodape; ?></p>
          </div>
        </div>        <div class="col-md-7 col-lg-4">
          <h4 style="color:#fff;">Links Contatos</h4>
          <div class="contact_nav footer-col">
            <a href="https://goo.gl/maps/6XJLNBjYMdBqLGZHA" target="_blank" rel="noopener noreferrer" aria-label="Abrir endereço no Google Maps">
              <i class="fa fa-map-marker" aria-hidden="true"></i>
              <span><?php echo $endereco_sistema; ?></span>
            </a>

            <a href="<?php echo htmlspecialchars($bb_footer_url_assinatura, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" aria-label="Abrir página Assinatura ">
              <i class="fa fa-file-text-o" aria-hidden="true"></i>
              <span>Assinatura</span>
            </a>

            <a href="<?php echo htmlspecialchars($bb_footer_url_agendamentos, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" aria-label="Abrir página de agendamentoss">
              <i class="fa fa-cut" aria-hidden="true"></i>
              <span>Agendar</span>
            </a>

            <a href="<?php echo htmlspecialchars($bb_footer_url_produtos, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" aria-label="Abrir página de produtos">
              <i class="fa fa-scissors" aria-hidden="true"></i>
              <span>Produtos</span>
            </a>

            <a href="<?php echo htmlspecialchars($bb_footer_url_servicos, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" aria-label="Abrir página de Serviços">
              <i class="fa fa-user" aria-hidden="true"></i>
              <span>Serviços</span>
            </a>
          </div>
        </div>
        <div class="col-lg-3">
          <div class="footer_form footer-col">
            <h4 style="color:#fff;">SOLICITE AVALIAÇÃO</h4>
            <p style="color:#ccc;"></p>

            <form id="form_cadastro" action="<?php echo htmlspecialchars($bb_footer_url_cadastrar, ENT_QUOTES, 'UTF-8'); ?>" method="POST" data-recaptcha-action="cadastro">
              <input type="hidden" name="recaptcha_token" value="">
              <input type="hidden" name="recaptcha_action" value="cadastro">
              <?php echo $bb_footer_hidden_loja; ?>

              <input
                type="tel"
                name="telefone"
                id="telefone_rodape"
                placeholder="(49) 99999-9999"
                class="form-control mb-2 js-mascara-telefone-br"
                required
                autocomplete="tel"
                inputmode="numeric"
                maxlength="15"
              >

              <input
                type="text"
                name="nome"
                placeholder="Nome Completo"
                class="form-control mb-2"
                required
                autocomplete="name"
              >

              <button type="submit" class="btn btn-theme btn-lg btn-block">Cadastrar</button>
            </form>

            <br>
            <small><div id="mensagem-rodape"></div></small>
          </div>
        </div>
      </div>
    </div>
  </div>

  <section id="bottom-footer" class="mt-4" style="margin-top:0;border-top:0;">
    <div class="container">
      <div class="row">
        <div class="col-md-6 col-sm-6 col-xs-12 btm-footer-links ocultar-mobile">
          <a style="color:#FFF;">&copy; <script>document.write(new Date().getFullYear())</script></a>

          <a href="<?php echo htmlspecialchars($bb_footer_url_politica, ENT_QUOTES, 'UTF-8'); ?>" style="color:#FFF;" aria-label="Abrir política de privacidade">
            <i class="fa fa-lock" aria-hidden="true"></i> Política de privacidade
          </a>

          <a href="<?php echo htmlspecialchars($bb_footer_url_termos, ENT_QUOTES, 'UTF-8'); ?>" class="text-white" aria-label="Abrir termos de uso">
            <i class="fa fa-file-text-o" aria-hidden="true"></i> Termos de uso
          </a>

          <a href="https://play.google.com/store/apps/details?id=app.agendacabeleireiro&pli=1" target="_blank" rel="noopener noreferrer" style="color:#FFF;" aria-label="Abrir aplicativo na Google Play">
            <i class="fa fa-play store" aria-hidden="true"></i> App
          </a>
        </div>

        <div class="col-md-6 col-sm-6 col-xs-12 copyright text-right">
          <span class="cor-branca ocultar-mobile"><?php echo $nome_sistema; ?></span>

          <a
            href="http://api.whatsapp.com/send?1=pt_BR&phone=<?php echo $tel_whatsapp; ?>"
            target="_blank"
            rel="noopener noreferrer"
            style="color:#FFF;"
            aria-label="Falar no WhatsApp"
          >
            <?php echo $whatsapp_sistema; ?> |
          </a>

          <a title="Ir para o sistema" href="<?php echo htmlspecialchars($bb_footer_url_sistema, ENT_QUOTES, 'UTF-8'); ?>" style="color:#FFF;" target="_blank" rel="noopener noreferrer" aria-label="Acessar sistema">
            <i class="fa fa-user" aria-hidden="true"></i>
          </a>
        </div>
      </div>
    </div>
  </section>
</footer>

<?php unset($_SESSION['editar_agendamento']); ?>

<!-- jQuery (local) -->
<script src="/js/jquery-3.4.1.min.js" defer></script>

<!-- Popper (CDN com SRI) -->
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous" defer></script>

<!-- Bootstrap (local) -->
<script src="/js/bootstrap.js" defer></script>

<!-- OwlCarousel (CDN) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js" defer></script>

<!-- Custom (local) -->
<script src="/js/custom.js" defer></script>

<!-- Google Maps: carrega somente quando existir mapa JS antigo na página -->
<script>
window.myMap = window.myMap || function(){};
window.addEventListener('load', function(){
  setTimeout(function(){
    if (!document.getElementById('googleMap') && !document.querySelector('[data-google-map]')) {
      return;
    }

    var s = document.createElement('script');
    s.src = 'https://maps.googleapis.com/maps/api/js?key=AIzaSyCh39n5U-4IoWpsVGUHWdqB6puEkhRLdmI&callback=myMap';
    s.async = true;
    s.defer = true;
    document.head.appendChild(s);
  }, 2500);
}, { once:true });
</script>

<!-- Mascaras JS (carregar apenas uma vez) -->
<script src="/sistema/painel/js/mascaras.js" type="text/javascript" defer></script>

<!-- jQuery Mask (para funcionar mascaras) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.11/jquery.mask.min.js" defer></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>

<script>
window.jacyOnReady = window.jacyOnReady || function(callback){
  function run(){
    if (window.jQuery && typeof callback === 'function') {
      callback(window.jQuery);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run, { once:true });
  } else {
    run();
  }
};
</script>

<!-- Modal Empresa -->
<div id="empresa" class="modal fade" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="">
        <div class="modal-header">
          <h1 class="modal-title"><small>BARBEIRO - CABELEIREIRO</small></h1>
          <button type="submit" class="close" name="fecharModal" aria-label="Fechar">&times;</button>
        </div>
      </form>

      <div class="modal-body">
        <p class="text-muted">
          <small>
            Com mais de 25 anos de experiência, Jacy Cordeiro atende com foco em técnica, acabamento e orientação personalizada.<br>
            Especializado em cortes, barba e prótese capilar, trabalha com avaliação individual para indicar o melhor caminho de acordo com o perfil de cada cliente.
            <br><br>
            💇‍♂️ Cabeleireiro &amp; Barbeiro <br>
            ✨ Especialista em Prótese Capilar <br>
            📆 Atendimento com hora marcada <br>
            ✅ Aplicação, manutenção e acabamento natural<br>
            Agende uma avaliação e converse diretamente com o profissional.
          </small>
        </p>

        <p class="text-muted">
          <small>
            Jacy Cordeiro - CNPJ: 38.896.614/0001-40.<br>
            Assista o vídeo a seguir.
          </small>
        </p>

        <iframe
          width="100%"
          height="500"
          src="https://www.youtube.com/embed/fO_s7jlO1tU?si=n3Yntmowyfms6aFm&amp;controls=0"
          frameborder="0"
          allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
          allowfullscreen
          title="Vídeo institucional Jacy Cabeleireiro"
        ></iframe>

        <p class="text-muted" align="center">
          <small>"Motivação é aquilo que te faz começar. Habito é aquilo que te faz continuar."</small>
        </p>
      </div>
    </div>
  </div>
</div>

<!-- Modal Termos (Bootstrap 4.3.1) -->
<div id="modalTermos" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="tituloTermos" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="tituloTermos">Termos de Serviço — Jacy Cabeleireiro</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <p><strong>1. Termos</strong></p>
        <p>Ao acessar o site Jacy Cabeleireiro, você concorda em cumprir estes termos de serviço, todas as leis e regulamentos aplicáveis e declara ser responsável pelo cumprimento de todas as leis locais. Se não concordar com algum termo, fica proibido de usar ou acessar este site. Os materiais aqui contidos são protegidos pelas leis de direitos autorais e marcas.</p>

        <p><strong>2. Uso de Licença</strong></p>
        <p>É concedida permissão para baixar temporariamente uma cópia dos materiais (informações ou software) do site Jacy Cabeleireiro somente para visualização pessoal e não comercial. Esta é a concessão de uma licença, não uma transferência de título, e sob esta licença você <u>não pode</u>:</p>
        <ul>
          <li>Modificar ou copiar os materiais;</li>
          <li>Usar os materiais para qualquer finalidade comercial ou exibição pública;</li>
          <li>Tentar descompilar ou fazer engenharia reversa de qualquer software do site;</li>
          <li>Remover direitos autorais ou outras notações de propriedade dos materiais;</li>
          <li>Transferir os materiais para outra pessoa ou “espelhar” em outro servidor.</li>
        </ul>
        <p>Esta licença termina automaticamente se você violar alguma restrição e pode ser rescindida pelo Jacy Cabeleireiro a qualquer momento. Ao encerrar a visualização, apague todos os materiais baixados (eletrônicos ou impressos).</p>

        <p><strong>3. Isenção de responsabilidade</strong></p>
        <p>Os materiais no site são fornecidos “no estado em que se encontram”. O Jacy Cabeleireiro não oferece garantias, expressas ou implícitas, incluindo, sem limitação, garantias de comerciabilidade, adequação a um fim específico ou não violação. Não garante precisão, resultados ou confiabilidade do uso dos materiais, próprios ou de sites vinculados.</p>

        <p><strong>4. Limitações</strong></p>
        <p>Em nenhum caso o Jacy Cabeleireiro ou seus fornecedores serão responsáveis por danos (incluindo perda de dados ou lucros, ou interrupção de negócios) decorrentes do uso ou incapacidade de usar os materiais, mesmo que notificado da possibilidade de tais danos. Algumas jurisdições não permitem tais limitações; elas podem não se aplicar a você.</p>

        <p><strong>5. Precisão dos materiais</strong></p>
        <p>Os materiais exibidos podem conter erros técnicos, tipográficos ou fotográficos. O Jacy Cabeleireiro pode alterar os materiais a qualquer momento, sem aviso, e não se compromete a atualizá-los.</p>

        <p><strong>6. Links</strong></p>
        <p>O Jacy Cabeleireiro não analisou todos os sites vinculados e não é responsável por seu conteúdo. A inclusão de qualquer link não implica endosso. O uso de sites vinculados é por conta e risco do usuário.</p>

        <p><strong>Modificações</strong></p>
        <p>O Jacy Cabeleireiro pode revisar estes termos a qualquer momento, sem aviso. Ao usar este site, você concorda em ficar vinculado à versão vigente.</p>

        <p class="mb-0"><strong>Lei aplicável</strong></p>
        <p>Estes termos são regidos e interpretados de acordo com as leis aplicáveis ao Jacy Cabeleireiro, e você se submete à jurisdição exclusiva dos tribunais competentes.</p>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Fechar</button>
        <button type="button" class="btn btn-primary" data-dismiss="modal">Ok, entendi</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Privacidade (Bootstrap 4.3.1) -->
<div id="privacidade" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="tituloPrivacidade" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="tituloPrivacidade">Política de Privacidade — Jacy Cabeleireiro</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <p><strong>Política de Privacidade</strong></p>
        <p>
          A sua privacidade é importante para nós. É política do Jacy Cabeleireiro respeitar a sua privacidade em relação
          a qualquer informação sua que possamos coletar no site Jacy Cabeleireiro, e outros sites que possuímos e operamos.
        </p>
        <p>
          Solicitamos informações pessoais apenas quando realmente precisamos delas para lhe fornecer um serviço. Fazemo-lo
          por meios justos e legais, com o seu conhecimento e consentimento. Também informamos por que estamos coletando
          e como será usado.
        </p>
        <p>
          Apenas retemos as informações coletadas pelo tempo necessário para fornecer o serviço solicitado. Quando armazenamos
          dados, protegemos dentro de meios comercialmente aceitáveis ​​para evitar perdas e roubos, bem como acesso, divulgação,
          cópia, uso ou modificação não autorizados.
        </p>
        <p>
          Não compartilhamos informações de identificação pessoal publicamente ou com terceiros, exceto quando exigido por lei.
        </p>
        <p>
          O nosso site pode ter links para sites externos que não são operados por nós. Esteja ciente de que não temos controle
          sobre o conteúdo e práticas desses sites e não podemos aceitar responsabilidade por suas respectivas políticas de privacidade.
        </p>
        <p>
          Você é livre para recusar a nossa solicitação de informações pessoais, entendendo que talvez não possamos fornecer alguns
          dos serviços desejados.
        </p>
        <p>
          O uso continuado de nosso site será considerado como aceitação de nossas práticas em torno de privacidade e informações
          pessoais. Se você tiver alguma dúvida sobre como lidamos com dados do usuário e informações pessoais, entre em contacto
          connosco.
        </p>

        <p><strong>Cookies, Google Ads e medição de campanhas</strong></p>
        <p>
          Podemos utilizar cookies, Google Ads, Google Analytics, Google Tag Manager, reCAPTCHA e tags de conversão para proteger formulários,
          medir visitas, entender a origem dos contatos e melhorar a experiência do usuário.
        </p>
        <p>
          Cliques em WhatsApp, botões de agendamento, envio de formulário e solicitação de simulação podem ser medidos como conversões.
          Esses dados ajudam a avaliar o desempenho das campanhas sem publicar informações pessoais do usuário no site.
        </p>
        <p>
          Quando o usuário envia nome, WhatsApp ou foto para simulação, essas informações são usadas apenas para atendimento, contato solicitado
          e geração da prévia visual. O usuário pode solicitar correção ou exclusão de dados pelo WhatsApp informado no site.
        </p>

        <p><strong>Compromisso do Usuário</strong></p>
        <p>O usuário se compromete a fazer uso adequado dos conteúdos e da informação que o Jacy Cabeleireiro oferece no site e, de forma enunciativa porém não limitativa:</p>
        <ul>
          <li>A) Não se envolver em atividades que sejam ilegais ou contrárias à boa-fé e à ordem pública;</li>
          <li>B) Não difundir propaganda ou conteúdo de natureza racista, xenofóbica, jogos de sorte ou azar, qualquer tipo de pornografia ilegal, apologia ao terrorismo ou contra os direitos humanos;</li>
          <li>C) Não causar danos aos sistemas físicos (hardware) e lógicos (software) do Jacy Cabeleireiro, de seus fornecedores ou terceiros, nem introduzir ou disseminar vírus informáticos ou quaisquer outros sistemas capazes de causar os danos mencionados.</li>
        </ul>

        <p><strong>Mais informações</strong></p>
        <p>
          Esperamos que esteja esclarecido e, como mencionado anteriormente, se houver algo que você não tem certeza se precisa ou não,
          geralmente é mais seguro deixar os cookies ativados, caso interaja com um dos recursos que você usa em nosso site.
        </p>

        <p class="mb-0"><small><em>Esta política foi atualizada em 26/05/2026.</em></small></p>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Fechar</button>
        <button type="button" class="btn btn-primary" data-dismiss="modal">Ok, entendi</button>
      </div>
    </div>
  </div>
</div>

<!-- ============= MÁSCARA DE WHATSAPP (Modal Lead) ============= -->
<script>
window.jacyOnReady(function($) {
  var $zap = $('#leadZap');
  if ($zap.length === 0) return;

  $zap.attr({
    'inputmode': 'numeric',
    'autocomplete': 'tel'
  });

  function onlyDigits(v) {
    return (v || '').replace(/\D+/g, '').slice(0, 11);
  }

  function formatBR(d) {
    if (d.length <= 2) return d;
    if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
    if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
    return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
  }

  $zap.on('input', function() {
    var raw = $(this).val();
    var digits = onlyDigits(raw);
    $(this).val(formatBR(digits));
  });

  $zap.on('paste', function(e) {
    e.preventDefault();
    var clip = (e.originalEvent.clipboardData || window.clipboardData).getData('text') || '';
    $(this).val(formatBR(onlyDigits(clip)));
  });
});
</script>

<!-- ============= ENVIO AJAX - MARCA CLIENTE COMO CADASTRADO ============= -->
<script>
window.jacyOnReady(function($) {
  var $form = $('#leadForm');
  if ($form.length === 0) return;

  var $btn = $('#leadBtn');

  function onlyDigits(v) {
    return (v || '').replace(/\D+/g, '');
  }

  $form.on('submit', function(e) {
    e.preventDefault();

    var nome = $('#leadNome').val().trim();
    var fone = onlyDigits($('#leadZap').val());

    if (!nome || fone.length < 10) {
      if (window.Swal) {
        Swal.fire({
          icon: 'error',
          title: '❌ Dados inválidos',
          text: 'Preencha corretamente nome e telefone.',
          confirmButtonColor: '#b8862d'
        });
      } else {
        alert('Preencha corretamente nome e telefone.');
      }
      return;
    }

    var oldText = $btn.text();
    $btn.prop('disabled', true).text('Enviando...');

    $.ajax({
      url: <?php echo json_encode($bb_footer_url_cadastrar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
      method: 'POST',
      data: $form.serialize(),
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      success: function(resp) {
        var texto = (resp || '').replace(/\s+/g, ' ').trim();

        if (texto.indexOf('Cadastrado com Sucesso') === 0 || texto.indexOf('Salvo com Sucesso') === 0) {
          if (typeof window.marcarClienteCadastrado === 'function') {
            window.marcarClienteCadastrado();
          }

          if (typeof window.hideLeadModal === 'function') {
            window.hideLeadModal();
          }

          if (window.Swal) {
            Swal.fire({
              icon: 'success',
              title: '✅ Sucesso!',
              text: 'Cadastro realizado com sucesso.',
              confirmButtonColor: '#b8862d'
            }).then(function() {
              window.location.href = <?php echo json_encode($bb_footer_url_acesso_cliente, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            });
          } else {
            window.location.href = <?php echo json_encode($bb_footer_url_acesso_cliente, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
          }
          return;
        }

        if (texto.indexOf('Você já está Cadastrado') === 0) {
          if (typeof window.marcarClienteCadastrado === 'function') {
            window.marcarClienteCadastrado();
          }

          if (typeof window.hideLeadModal === 'function') {
            window.hideLeadModal();
          }

          if (window.Swal) {
            Swal.fire({
              icon: 'warning',
              title: '⚠️ Já cadastrado',
              text: 'Este WhatsApp já possui cadastro.',
              confirmButtonColor: '#b8862d'
            }).then(function() {
              window.location.href = <?php echo json_encode($bb_footer_url_acesso_cliente, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            });
          } else {
            window.location.href = <?php echo json_encode($bb_footer_url_acesso_cliente, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
          }
          return;
        }

        if (texto.indexOf('Preencha nome e telefone') === 0) {
          if (window.Swal) {
            Swal.fire({
              icon: 'error',
              title: '❌ Dados inválidos',
              text: 'Preencha corretamente nome e telefone.',
              confirmButtonColor: '#b8862d'
            });
          } else {
            alert('Preencha corretamente nome e telefone.');
          }
          return;
        }

        if (window.Swal) {
          Swal.fire({
            icon: 'error',
            title: '❌ Erro inesperado',
            html: 'Retorno:<br><pre style="white-space:pre-wrap">' + texto + '</pre>',
            confirmButtonColor: '#b8862d'
          });
        } else {
          alert('Erro: ' + texto);
        }
      },
      error: function() {
        if (window.Swal) {
          Swal.fire({
            icon: 'error',
            title: '❌ Falha de rede',
            text: 'Não foi possível enviar os dados.',
            confirmButtonColor: '#b8862d'
          });
        } else {
          alert('Falha de rede.');
        }
      },
      complete: function() {
        $btn.prop('disabled', false).text(oldText);
      }
    });
  });
});
</script>

<!-- ============= SINCRONIZAÇÃO HEADER SPACER ============= -->
<script>
window.jacyOnReady(function($) {
  function syncSpacer() {
    var $header = $('.header_section');
    var $spacer = $('#header-spacer');
    if ($header.length && $spacer.length) {
      $spacer.css('height', $header.outerHeight() + 'px');
    }
  }

  function onScroll() {
    var $header = $('.header_section');
    if ($header.length) {
      if ($(window).scrollTop() > 8) {
        $header.addClass('header--scrolled');
      } else {
        $header.removeClass('header--scrolled');
      }
    }
  }

  $(window).on('load resize orientationchange', syncSpacer);
  $(window).on('scroll', onScroll);
  $(document).on('shown.bs.collapse hidden.bs.collapse', syncSpacer);
});
</script>

<!-- ============= FORMULÁRIO RODAPÉ - AJAX ============= -->
<script>
window.jacyOnReady(function($) {
  var $tel = $('#telefone_rodape');

  function jacySomenteDigitos(valor) {
    return String(valor || '').replace(/\D/g, '').slice(0, 11);
  }

  function jacyMascaraTelefoneBR(valor) {
    var d = jacySomenteDigitos(valor);

    if (d.length === 0) return '';
    if (d.length <= 2) return '(' + d;
    if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
    if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);

    return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7, 11);
  }

  if ($tel.length) {
    $tel.attr('maxlength', '15');

    $tel.on('input keyup change paste blur', function() {
      var formatado = jacyMascaraTelefoneBR(this.value);
      if (this.value !== formatado) {
        this.value = formatado;
      }
    });

    if ($tel.val()) {
      $tel.val(jacyMascaraTelefoneBR($tel.val()));
    }
  }

  var $form = $('#form_cadastro');
  if ($form.length === 0) return;

  $form.on('submit', function(e) {
    e.preventDefault();

    var enviarCadastroRodape = function(){
      $.ajax({
        url: <?php echo json_encode($bb_footer_url_cadastrar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
      method: 'POST',
      data: $form.serialize(),
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      success: function(resp) {
        var texto = (resp || '').replace(/\s+/g, ' ').trim();

        if (texto.indexOf('Cadastrado com Sucesso') === 0) {
          Swal.fire({
            icon: 'success',
            title: '✅ Sucesso!',
            text: 'Cadastro realizado com sucesso.',
            confirmButtonColor: '#DAA520'
          }).then(function() {
            window.location.href = <?php echo json_encode($bb_footer_url_acesso_cliente, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
          });
        } else if (texto.indexOf('Você já está Cadastrado') === 0) {
          Swal.fire({
            icon: 'warning',
            title: '⚠️ Já cadastrado',
            text: 'Você já tem cadastro.',
            confirmButtonColor: '#DAA520'
          }).then(function() {
            window.location.href = <?php echo json_encode($bb_footer_url_acesso_cliente, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
          });
        } else if (texto.indexOf('Preencha nome e telefone') === 0) {
          Swal.fire({
            icon: 'error',
            title: '❌ Dados inválidos',
            text: 'Preencha corretamente nome e telefone.',
            confirmButtonColor: '#DAA520'
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: '❌ Erro inesperado',
            html: 'Retorno:<br><pre style="white-space:pre-wrap">' + texto + '</pre>',
            confirmButtonColor: '#DAA520'
          });
        }
      },
      error: function() {
        Swal.fire({
          icon: 'error',
          title: '❌ Falha de rede',
          text: 'Não foi possível enviar os dados.',
          confirmButtonColor: '#DAA520'
        });
      }
      });
    };

    if (window.jacyPrepareRecaptcha) {
      window.jacyPrepareRecaptcha($form[0], 'cadastro').then(enviarCadastroRodape).catch(enviarCadastroRodape);
    } else {
      enviarCadastroRodape();
    }
  });
});
</script>

</body>
</html>