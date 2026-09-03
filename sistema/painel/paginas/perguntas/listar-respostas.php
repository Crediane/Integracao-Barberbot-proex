<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'respostas';
$id_aluno = $_SESSION['id'];

$id_pergunta = $_POST['id'];

$query_m = $pdo->query("SELECT * FROM $tabela where pergunta = '$id_pergunta' order by id desc");
$res_m = $query_m->fetchAll(PDO::FETCH_ASSOC);
for($i_m=0; $i_m < @count($res_m); $i_m++){
	foreach ($res_m[$i_m] as $key => $value){}
$id = $res_m[$i_m]['id'];
$resposta = $res_m[$i_m]['resposta'];
$data = $res_m[$i_m]['data'];
$dataF = implode('/', array_reverse(explode('-', $data)));
$aluno = $res_m[$i_m]['pessoa'];
$funcao = $res_m[$i_m]['funcao'];

if($funcao == 'Professor'){
	$texto_professor = 'Professor ';
	$painel = 'painel-admin';
}else{
	$texto_professor = '';
	$painel = 'painel-aluno';
}


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
<span> <img style="border-radius: 100%;" class="rounded-circle z-depth-0" src="../{$painel}/img/perfil/{$img_aluno}" width="25" height="25">  
<span class="text-muted"><b>{$texto_professor} {$nome}</b> </span>
<span class="text-muted" style="margin-left:10px">{$dataF}</span> 

	<li class="dropdown head-dpdn2" style="display: inline-block;">
		<a class="" href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false" title="Excluir Resposta"><big><i class="fa fa-trash-o text-danger "></i></big></a>

		<ul class="dropdown-menu" style="margin-left:-230px;">
		<li>
		<div class="notification_desc2">
		<p>Confirmar Exclusão? <a href="#" onclick="excluirResposta('{$id}')"><span class="text-danger">Sim</span></a></p>
		</div>
		</li>										
		</ul>
		</li>

<br>
<span class="text-muted"> <i>{$resposta}</i> </span></small>
</div>
<hr>
HTML;


}
?>		