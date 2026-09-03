<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'respostas';
$id_aluno = $_SESSION['id'];
$resposta = $_POST['resposta'];
$pergunta = $_POST['id_pergunta'];
$id_curso = $_POST['id_curso'];

$resposta = str_replace("'", " ", $resposta);

$query = $pdo->prepare("INSERT INTO $tabela SET  resposta = :resposta, curso = '$id_curso', pessoa = '$id_aluno', data = curDate(), pergunta = '$pergunta', funcao = 'Professor'");

$query->bindValue(":resposta", "$resposta");
$query->execute();

//atualizar a tabela de perguntas
$query = $pdo->query("UPDATE perguntas SET respondida = 'Sim' where id = '$pergunta'");

echo 'Salvo com Sucesso';

?>