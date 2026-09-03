<?php
require_once __DIR__ . '/config.php';
// tabela fixa para evitar interpolação de nome de tabela
$tabela = 'avaliacoes_site';

/*
  =========================================================
  NOVO: Filtra registro interno (is_config=1) se a coluna existir.
  - Se ainda não tiver feito o ALTER TABLE, não quebra:
    tenta com WHERE e se der erro cai no SELECT antigo.
  =========================================================
*/
$sql = "SELECT * FROM avaliacoes_site WHERE (is_config IS NULL OR is_config = 0) ORDER BY id DESC";

try {
  $query = $pdo->query($sql);
} catch (Throwable $e) {
  // fallback para bancos antigos (sem coluna is_config)
  $query = $pdo->query("SELECT * FROM avaliacoes_site ORDER BY id DESC");
}

$res   = $query->fetchAll(PDO::FETCH_ASSOC);
$total = @count($res);

if ($total > 0) {
echo <<<HTML
<small>
<table class="table table-hover" id="tabela-avaliacoes">
  <thead>
    <tr>
      <th>Cliente</th>
      <th class="esc">Nota</th>
      <th class="esc">Comentário</th>
      <th class="esc">Origem</th>
      <th class="esc">Data</th>
      <th class="esc">Status</th>
      <th>Ações</th>
    </tr>
  </thead>
  <tbody>
HTML;

  for ($i = 0; $i < $total; $i++) {

    // Se por algum motivo veio registro interno (fallback), pula aqui também
    if (isset($res[$i]['is_config']) && (int)$res[$i]['is_config'] === 1) {
      continue;
    }

    $id     = (int)($res[$i]['id'] ?? 0);
    $nome   = $res[$i]['nome'] ?? '';
    $notaBD = (int)($res[$i]['nota'] ?? 0);
    $coment = $res[$i]['comentario'] ?? '';
    $origem = $res[$i]['origem'] ?? 'Site';
    $data   = $res[$i]['data_avaliacao'] ?? null;
    $foto   = $res[$i]['foto'] ?? '';
    $status = (int)($res[$i]['status'] ?? 0);

    // avatar: inicial do nome ou foto do perfil
    $nomeTrim = trim($nome);
    $inic     = $nomeTrim !== '' ? mb_strtoupper(mb_substr($nomeTrim, 0, 1)) : '?';

    $avatar = '<div style="width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#4285f4;color:#fff;font-weight:700;font-size:16px;">'.$inic.'</div>';

    if (!empty($foto)) {
      $fotoEsc = htmlspecialchars($foto, ENT_QUOTES, 'UTF-8');
      $avatar  = '<img src="'.$fotoEsc.'" style="width:34px;height:34px;border-radius:50%;object-fit:cover" onerror="this.style.display=\'none\'">';
    }

    // nota (separar valor numérico da renderização em estrelas)
    $notaValor = max(0, min(5, $notaBD));
    $stars     = str_repeat('⭐', $notaValor) . str_repeat('☆', 5 - $notaValor);
    $stars     = '<span style="color:#f4b400;font-size:16px">'.$stars.'</span>';

    // comentário resumido
    $comentF = trim($coment);
    if (mb_strlen($comentF) > 80) {
      $comentF = mb_substr($comentF, 0, 80).'...';
    }

    if ($comentF === '') {
      $comentF = '<em class="text-muted">Sem comentário</em>';
    } else {
      $comentF = htmlspecialchars($comentF, ENT_QUOTES, 'UTF-8');
    }

    // origem (badge)
    $cores = [
      'Google'    => '#34a853',
      'Instagram' => '#c13584',
      'Facebook'  => '#1877f2',
      'Site'      => '#6c757d',
      'Outro'     => '#6c757d',
    ];
    $cor         = $cores[$origem] ?? '#6c757d';
    $origemEsc   = htmlspecialchars($origem, ENT_QUOTES, 'UTF-8');
    $badgeOrigem = '<span class="badge" style="background:'.$cor.';color:#fff;font-size:11px;min-width:60px;text-align:center">'.$origemEsc.'</span>';

    // data
    $dataF = $data ? date('d/m/Y', strtotime($data)) : '-';

    // status
    $statusBadge = $status
      ? '<span class="badge badge-success" title="Publicado">✓</span>'
      : '<span class="badge badge-secondary" title="Rascunho">○</span>';

    /*
      =========================================================
      NOVO: Escape correto para parâmetros no onclick
      - Usa json_encode pra não quebrar quando tiver aspas (')
      - Evita XSS no inline JS
      =========================================================
    */
    $nomeJs   = json_encode($nome,   JSON_UNESCAPED_UNICODE);
    $comentJs = json_encode($coment, JSON_UNESCAPED_UNICODE);
    $origemJs = json_encode($origem, JSON_UNESCAPED_UNICODE);
    $dataJs   = json_encode($data ?? '', JSON_UNESCAPED_UNICODE);
    $fotoJs   = json_encode($foto ?? '', JSON_UNESCAPED_UNICODE);

    $nomeHtml = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');

    echo <<<HTML
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:10px;">
          $avatar
          <div>
            <div style="font-weight:600;font-size:14px">{$nomeHtml}</div>
          </div>
        </div>
      </td>

      <td class="esc">$stars</td>
      <td class="esc" style="max-width:250px">{$comentF}</td>
      <td class="esc">{$badgeOrigem}</td>
      <td class="esc">{$dataF}</td>
      <td class="esc">{$statusBadge}</td>

      <td>
        <big>
          <a href="#" onclick="ver($id, $nomeJs, $notaValor, $comentJs, $origemJs, $dataJs, $fotoJs, $status)" title="Ver Detalhes">
            <i class="fa fa-info-circle text-primary"></i>
          </a>
        </big>

        <big>
          <a href="#" onclick="editar($id, $nomeJs, $notaValor, $comentJs, $origemJs, $dataJs, $fotoJs, $status)" title="Editar">
            <i class="fa fa-edit text-success"></i>
          </a>
        </big>

        <li class="dropdown head-dpdn2" style="display:inline-block;">
          <a href="#" class="dropdown-toggle" data-toggle="dropdown">
            <big><i class="fa fa-trash-o text-danger"></i></big>
          </a>
          <ul class="dropdown-menu" style="margin-left:-230px;">
            <li>
              <div class="notification_desc2">
                <p>Confirmar Exclusão? <a href="#" class="btnExcluir" data-id="{$id}"><span class="text-danger">Sim</span></a></p>
              </div>
            </li>
          </ul>
        </li>
      </td>
    </tr>
HTML;
  }

echo <<<HTML
  </tbody>
</table>
</small>
HTML;

} else {
  echo '<div class="alert alert-info"><i class="fa fa-info-circle"></i> Nenhuma avaliação cadastrada ainda. Clique em "Nova Avaliação" para adicionar.</div>';
}
?>

<script>
$(document).ready(function(){
  if (!$('#tabela-avaliacoes').length) {
    return;
  }

  if ($.fn.DataTable.isDataTable('#tabela-avaliacoes')) {
    $('#tabela-avaliacoes').DataTable().destroy();
  }

  $('#tabela-avaliacoes').DataTable({
    ordering: true,
    stateSave: true,
    language: {
      url: "//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json"
    }
  });

  $('#tabela-avaliacoes_filter label input').focus();
});
</script>