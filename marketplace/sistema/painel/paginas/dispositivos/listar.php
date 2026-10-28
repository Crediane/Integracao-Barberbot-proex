<?php
require_once __DIR__ . '/../_context_bootstrap.php';
$id_usuario   = @$_SESSION['id'];
$nome_usuario = $_SESSION['nome'];

$tabela = 'dispositivos';

require_once __DIR__ . '/../../../conexao.php';

$data_atual = date('Y-m-d');
$dataAtual  = date("Y-m-d");

$query = $pdo->prepare("SELECT * FROM $tabela WHERE status_api IS NOT NULL");
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);

if ($res) {

echo <<<HTML
<small>
<table class="table table-hover text-center">
  <thead class="thead-light">
    <tr>
      <th scope="col" class="esc" style="padding-left: 50px !important;">ID</th>
      <th scope="col" class="esc" style="padding-left: 50px !important;">Telefone</th>
      <th scope="col" class="esc" style="padding-left: 190px !important;">Appkey</th>
      <th scope="col" class="esc" style="padding-left: 100px !important;">Status</th>
      <th scope="col" class="esc" style="padding-left: 120px !important;">Ações</th>
    </tr>
  </thead>
  <tbody>
HTML;

  $i = 0;
  foreach ($res as $dispositivos) {
    $i++;
    $id       = $dispositivos['id'];
    $appkey   = $dispositivos['appkey'];
    $status   = $dispositivos['status'];
    $telefone = $dispositivos['telefone'] ?? '';
    $nucleo   = $dispositivos['nucleo'] ?? '';

    ?>
    <tr class="tabelaResultados">
      <td class="esc"><?= $i; ?></td>
      <td class="esc"><?= htmlspecialchars($telefone); ?></td>
      <td class="esc"><?= htmlspecialchars($appkey); ?></td>
      <td class="esc"><?= htmlspecialchars($status); ?></td>
      <td class="esc">

        <li class="dropdown head-dpdn2" style="display:inline-block;">
          <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
            <big><i class="fa fa-trash-o text-danger"></i></big>
          </a>
          <ul class="dropdown-menu" style="margin-left:-230px;">
            <li>
              <div class="notification_desc2">
                <p>Confirmar Exclusão?
                  <a href="#" onclick="excluir('<?= $id; ?>'); return false;">
                    <span class="text-danger">Sim</span>
                  </a>
                </p>
              </div>
            </li>
          </ul>
        </li>

        <big>
          <a href="#" onclick="add('<?= $appkey; ?>'); $('#modaldispositivo').modal('show'); return false;" title="Reconectar Dispositivo">
            <i class="fa fa-wifi" style="color:#3d1002"></i>
          </a>
        </big>

        <big>
          <!-- Teste de disparo (Menuia) com loader e mensagem amigável -->
          <a href="#" onclick="testarApi(this,'<?= $appkey; ?>'); return false;" title="Testar Disparo">
            <i class="fa fa-whatsapp" style="color:green"></i>
          </a>
        </big>

      </td>
    </tr>
    <?php
  }

echo <<<HTML
  </tbody>
</table>

<small><div align="center" id="mensagem-excluir"></div></small>

<br>
<div align="right"></div>
HTML;

} else {
  echo '<small>Nenhum Registro Encontrado!</small>';
}
?>

<script>
  function alterarStatus(status, id = '') {
    if (status !== 'bloqueado') {
      $.ajax({
        url: bb_url('paginas/' + pag + '/status.php'),
        method: 'POST',
        data: { status: status, id: id },
        dataType: 'text',
        success: function () { listar(); location.reload(); },
        error: function (xhr, st, err) { console.error('Erro na requisição:', err); }
      });
    } else {
      $.ajax({
        url: bb_url('paginas/' + pag + '/excluir.php'),
        method: 'POST',
        data: { id: id },
        dataType: 'text',
        success: function () { listar(); location.reload(); },
        error: function (xhr, st, err) { console.error('Erro na requisição:', err); }
      });
    }
  }
</script>

<script type="text/javascript">
  // Testar disparo via WhatsApp (Menuia)
  // Chama o "bridge" local testar.php no mesmo diretório (evita 404 de caminhos).
  // Aplica spinner no ícone e mostra mensagem amigável com parse de JSON.
  function testarApi(el, token) {
    var telefone_sistema = '<?= $whatsapp_sistema; ?>';
    var instancia        = '<?= $instancia_whatsapp; ?>';
    var seletor_api      = 'menuia';

    var $icon = $(el).find('i');
    var oldClass = $icon.attr('class');
    $icon.attr('class', 'fa fa-circle-notch fa-spin');

    $.ajax({
      url: bb_url('paginas/' + pag + '/testar.php'),
      method: 'POST',
      data: { seletor_api: seletor_api, token: token, instancia: instancia, telefone_sistema: telefone_sistema },
      dataType: 'text', // retorna texto; abaixo tentamos parsear como JSON
      success: function (result) {
        try {
          var json = (typeof result === 'string') ? JSON.parse(result) : result;
          // Monta mensagem amigável caso venha no formato {status, message, data:{idMensagem, dataEnvio}}
          var ok = (json && (json.status == 200 || json.status == '200'));
          var msgBase = ok ? '✅ Mensagem enviada!' : (json.message || 'Retorno recebido');
          var idMsg   = (json && json.data && json.data.idMensagem) ? json.data.idMensagem : '-';
          var quando  = (json && json.data && json.data.dataEnvio)   ? json.data.dataEnvio   : '-';
          var msg = msgBase + '\nID: ' + idMsg + '\nQuando: ' + quando;

          if (typeof alertWarning === 'function') { alertWarning(msg); } else { alert(msg); }
        } catch (e) {
          // Se não for JSON, exibe como veio
          if (typeof alertWarning === 'function') { alertWarning(result); } else { alert(result); }
        }
      },
      error: function (xhr) {
        var msg = 'Falha ao testar WhatsApp — HTTP ' + xhr.status + '.';
        if (xhr.responseText) msg += '\n' + xhr.responseText;
        if (typeof alertWarning === 'function') { alertWarning(msg); } else { alert(msg); }
        console.error(msg);
      },
      complete: function () {
        $icon.attr('class', oldClass); // restaura o ícone
      }
    });
  }
</script>
