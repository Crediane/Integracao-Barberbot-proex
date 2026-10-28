<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'perguntas';
$id_usuario = $_SESSION['id'];

$query = $pdo->query("SELECT DISTINCT * FROM perguntas where respondida != 'Sim' GROUP BY curso");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
if(@count($res) > 0){

for($i=0; $i < @count($res); $i++){
	foreach ($res[$i] as $key => $value){}

	$id_curso = $res[$i]['curso'];
	$query2 = $pdo->query("SELECT * FROM cursos where id = '$id_curso' and professor = '$id_usuario'");
	$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
	if(@count($res2) > 0){	
	
	$nome_curso = $res2[0]['nome'];
		$foto_curso = $res2[0]['imagem'];
		$nome_cursoF = mb_strimwidth($nome_curso, 0, 20, "...");
	


echo <<<HTML
	<a href="#" onclick="modalPerguntas('{$id_curso}', '{$nome_curso}')">
		<div class="col-md-2 col-sm-6 col-xs-6" style="margin-bottom: 20px">
			<img src="img/cursos/{$foto_curso}" width="100%">
			<p align="center"><small>{$nome_cursoF}</small> <br>
			</p>
		</div>
	</a>

HTML;

}

}

}else{
	echo 'Não existem perguntas pendentes de respostas!';
}