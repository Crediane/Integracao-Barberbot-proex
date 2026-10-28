<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'perguntas';
$id_aluno = $_SESSION['id'];

$id_do_curso_pag = $_POST['id'];

$query_m = $pdo->query("SELECT * FROM $tabela where curso = '$id_do_curso_pag' order by id desc");
$res_m = $query_m->fetchAll(PDO::FETCH_ASSOC);
for($i_m=0; $i_m < @count($res_m); $i_m++){
	foreach ($res_m[$i_m] as $key => $value){}
$id = $res_m[$i_m]['id'];
$pergunta = $res_m[$i_m]['pergunta'];
$aula = $res_m[$i_m]['aula'];
$data = $res_m[$i_m]['data'];
$dataF = implode('/', array_reverse(explode('-', $data)));
$aluno = $res_m[$i_m]['aluno'];
$respondida = $res_m[$i_m]['respondida'];


if($respondida != 'Sim'){
	$classe_pergunta = 'link-pergunta';
}else{
	$classe_pergunta = 'link-pergunta-respondida';
}

$query = $pdo->query("SELECT * FROM respostas where pergunta = '$id' order by id desc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$respostas = @count($res);

$query = $pdo->query("SELECT * FROM usuarios where id = '$aluno'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
if(@count($res) > 0){
	$nome = $res[0]['nome'];
$img_aluno = $res[0]['foto'];
}else{
	$nome = 'Sem Registro';
	$img_aluno = 'sem-perfil.jpg';
}

echo <<<HTML
<div class="mt-3">
<small>
<span> <img style="border-radius: 100%;" class="rounded-circle z-depth-0" src="../painel-aluno/img/perfil/{$img_aluno}" width="25" height="25">  
<span class="text-muted"><b>{$nome}</b> </span>
<span class="text-muted" style="margin-left:10px">{$dataF}</span> 
<span class="text-muted" style="margin-left:10px">{$respostas} Respostas</span> </span>

	<li class="dropdown head-dpdn2" style="display: inline-block;">
		<a class="" href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false" title="Excluir Pergunta"><big><i class="fa fa-trash-o text-danger "></i></big></a>

		<ul class="dropdown-menu" style="margin-left:-230px;">
		<li>
		<div class="notification_desc2">
		<p>Confirmar Exclusão? <a href="#" onclick="excluirPergunta('{$id}')"><span class="text-danger">Sim</span></a></p>
		</div>
		</li>										
		</ul>
		</li>

<br>
<span > <a class="{$classe_pergunta}" href="#" onclick="modalResposta('{$id}', '{$pergunta}')" title="Abrir Pergunta">Aula {$aula} - {$pergunta}</a> </span> <a title="Marcar como Respondida" href="#" onclick="respondida('{$id}')"><i class="fa fa-check verde "></i></a> </small>
</div>
<hr>
HTML;


}
?>		