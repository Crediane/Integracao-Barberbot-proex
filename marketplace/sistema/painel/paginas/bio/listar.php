<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'bio';

/* busca opcional */
$busca = trim($_POST['busca'] ?? '');
$where = '';
$params = [];
if($busca !== ''){
  $where = " WHERE (b.nome LIKE :b OR b.descricao LIKE :b OR b.chave LIKE :b) ";
  $params[':b'] = "%{$busca}%";
}

$sql = "SELECT b.*, ga.nome AS grupo_nome
        FROM {$tabela} b
        LEFT JOIN grupo_acessos ga ON ga.id=b.grupo
        {$where}
        ORDER BY COALESCE(b.ordem,0) ASC, b.id DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$res = $st->fetchAll(PDO::FETCH_ASSOC);
$total = @count($res);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function js($v){ return str_replace(["\r","\n","'"],[' ',' ',"\\'"], (string)$v); }
function img_url($v){
  $v = trim((string)$v);
  if($v==='') return '';
  if($v[0]=='/') return $v;
  return '/sistema/img/bio/'.$v;
}

if($total>0){
  echo <<<HTML
  <style>
    .thumb-bio{width:56px;height:56px;border-radius:12px;object-fit:cover;border:1px solid #e3e6ea;background:#fff}
    .thumb-ph{width:56px;height:56px;border-radius:12px;display:grid;place-items:center;border:1px solid #e3e6ea;background:#f4f6f8;color:#9aa3ad}
    .icon-preview{display:flex;align-items:center;gap:6px}
    .icon-preview i{font-size:18px}
    .col-url{max-width:360px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .desc-mini{color:#6c757d;font-size:.86rem}
    .td-actions i{font-size:20px}
  </style>
  <small>
  <table class="table table-hover" id="tabela">
    <thead>
      <tr>
        <th>Imagem</th>
        <th>Título</th>
        <th class="col-url">URL</th>
        <th>Grupo</th>
        <th>Ícone</th>
        <th>Ordem</th>
        <th>Status</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody>
  HTML;

  foreach($res as $r){
    $id   = (int)$r['id'];
    $nome = (string)$r['nome'];
    $desc = (string)$r['descricao'];
    $url  = (string)$r['chave'];
    $grp  = (string)($r['grupo_nome'] ?: 'Nenhum!');
    $ico  = (string)$r['icone'];
    $ord  = (int)$r['ordem'];
    $ati  = (int)$r['ativo'];
    $img  = img_url($r['imagem'] ?? '');
    $has  = $img !== '' && is_file($_SERVER['DOCUMENT_ROOT'].$img);

    $badge = $ati ? '<span class="badge badge-success">Ativo</span>' :
                    '<span class="badge badge-secondary">Inativo</span>';

    $toggle = $ati
      ? "<a href=\"#\" onclick=\"mudarAtivo({$id},0)\" title=\"Desativar\"><i class=\"fa fa-toggle-on text-success\"></i></a>"
      : "<a href=\"#\" onclick=\"mudarAtivo({$id},1)\" title=\"Ativar\"><i class=\"fa fa-toggle-off text-muted\"></i></a>";

    $icoPrev = $ico ? "<span class='icon-preview'><i class='".h($ico)."'></i><small>".h($ico)."</small></span>" : '<small class="text-muted">—</small>';
    $urlHtml = $url ? '<a href="'.h($url).'" target="_blank" rel="noopener">'.h($url).'</a>' : '<span class="text-muted">—</span>';
    $descMini = $desc ? '<div class="desc-mini">'.h(mb_strimwidth($desc,0,80,'…')).'</div>' : '';

    // parametros para a edição
    $p_nome = js($nome);
    $p_url  = js($url);
    $p_desc = js($desc);
    $p_ico  = js($ico);
    $p_img  = js($r['imagem'] ?? '');
    $p_grp  = (int)$r['grupo'];

    echo <<<HTML
      <tr>
        <td>
    HTML;
    if($has){
      $v = @filemtime($_SERVER['DOCUMENT_ROOT'].$img) ?: time();
      echo '<img class="thumb-bio" src="'.h($img).'?v='.$v.'" alt="'.h($nome).'">';
    }else{
      echo '<div class="thumb-ph"><i class="fa-regular fa-image"></i></div>';
    }
    echo <<<HTML
        </td>
        <td>
          <strong>{$nome}</strong>
          {$descMini}
        </td>
        <td class="col-url">{$urlHtml}</td>
        <td>{$grp}</td>
        <td>{$icoPrev}</td>
        <td>{$ord}</td>
        <td>{$badge}</td>
        <td class="td-actions">
          <a href="#" onclick="editar('{$id}','{$p_nome}','{$p_url}','{$p_grp}','{$p_desc}','{$p_ico}','{$ord}','{$ati}','{$p_img}')" title="Editar">
            <i class="fa fa-edit text-primary"></i>
          </a>
          &nbsp; {$toggle} &nbsp;
          <li class="dropdown head-dpdn2" style="display:inline-block;">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false" title="Excluir">
              <i class="fa fa-trash-o text-danger"></i>
            </a>
            <ul class="dropdown-menu" style="margin-left:-230px;">
              <li>
                <div class="notification_desc2">
                  <p>Confirmar Exclusão?
                    <a href="#" onclick="excluir('{$id}')"><span class="text-danger">Sim</span></a>
                  </p>
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
    <small><div align="center" id="mensagem-excluir"></div></small>
  </table>
  </small>
  HTML;

}else{
  echo '<small>Não possui nenhum registro Cadastrado!</small>';
}
?>

<script>
$(function(){
  $('#tabela').DataTable({
    "ordering": false,
    "stateSave": true
  });
  $('#tabela_filter label input').focus();
});

/* preenche o form para edição */
function editar(id, nome, url, grupo, descricao, icone, ordem, ativo, imagem){
  $('#titulo_inserir').text('Editar Registro');
  $('#id').val(id);
  $('#nome').val(nome);
  $('#chave').val(url);
  $('#grupo').val(grupo).trigger('change');
  $('#descricao').val(descricao);
  $('#icone').val(icone);
  $('#ordem').val(ordem);
  $('#ativo').val(ativo);
  $('#imagem_antiga').val(imagem);
  if(imagem){
    var src = (imagem.charAt(0)==='/' ? imagem : '/sistema/img/bio/'+imagem);
    $('#target').attr('src', src);
  }else{
    $('#target').attr('src','img/sem-foto.jpg');
  }
  $('#mensagem').text('');
  $('#modalForm').modal('show');
}

/* toggle ativo/inativo */
function mudarAtivo(id, valor){
  $.post('paginas/bio/mudar.php', {id:id, valor:valor}, function(msg){
    if($.trim(msg)=="Alterado com Sucesso"){
      listar();
    }else{
      alert(msg);
    }
  }).fail(function(){ alert('Erro ao alterar status'); });
}
</script>
