<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . "/../../../conexao.php");

$tabela = 'simulacoes_protese';
$pasta_upload = __DIR__ . '/../../images/simulacoes/';


$base_painel_publico = '/sistema/painel';
$caminho_requisicao_canvas = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

if ($caminho_requisicao_canvas) {
	if (preg_match('~^(/loja/[a-z0-9_-]+/painel)(?:/|$)~i', $caminho_requisicao_canvas, $mBaseLoja)) {
		$base_painel_publico = rtrim($mBaseLoja[1], '/');
	} elseif (preg_match('~^(/master/painel)(?:/|$)~i', $caminho_requisicao_canvas, $mBaseMaster)) {
		$base_painel_publico = rtrim($mBaseMaster[1], '/');
	} elseif (preg_match('~^(/sistema/painel)(?:/|$)~i', $caminho_requisicao_canvas, $mBaseSistema)) {
		$base_painel_publico = rtrim($mBaseSistema[1], '/');
	}
}


$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id <= 0){
	echo 'ID não informado';
	exit();
}

$query = $pdo->prepare("SELECT s.*, c.nome as nome_cliente, p.modelo 
	FROM $tabela s
	INNER JOIN clientes c ON s.cliente = c.id
	INNER JOIN proteses p ON s.id_protese = p.id
	WHERE s.id = :id");
$query->bindValue(":id", $id, PDO::PARAM_INT);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);

if(@count($res) == 0){
	echo 'Registro não encontrado';
	exit();
}

$cliente = $res[0]['nome_cliente'];
$modelo = $res[0]['modelo'];
$foto_original = $res[0]['foto_original'];
$imagem_simulada = $res[0]['imagem_simulada'];

if($foto_original == ""){
	echo 'Nenhuma foto original enviada';
	exit();
}

if(!is_dir($pasta_upload)){
	echo 'Pasta de imagens da simulação não encontrada';
	exit();
}

if(!is_readable($pasta_upload)){
	echo 'Pasta de imagens da simulação sem permissão de leitura';
	exit();
}

$foto_base = $pasta_upload . $foto_original;

if(!file_exists($foto_base)){
	echo 'Arquivo da foto original não encontrado';
	exit();
}

if(!is_file($foto_base)){
	echo 'Arquivo da foto original inválido';
	exit();
}

if(!is_readable($foto_base)){
	echo 'Arquivo da foto original sem permissão de leitura';
	exit();
}

$url_foto_original = rtrim($base_painel_publico, '/') . '/images/simulacoes/' . rawurlencode($foto_original) . '?v=' . time();
$url_imagem_simulada = '';

if($imagem_simulada != ''){
	$url_imagem_simulada = rtrim($base_painel_publico, '/') . '/images/simulacoes/' . rawurlencode($imagem_simulada) . '?v=' . time();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Canvas de Ajuste Fino</title>
	<link rel="stylesheet" href="../../css/bootstrap.min.css">

	<style type="text/css">
		body{
			background:#f4f6f9;
			font-family: Arial, sans-serif;
			padding:15px;
			color:#2d3436;
		}

		.topo-canvas{
			background:#ffffff;
			border-radius:10px;
			padding:16px 18px;
			box-shadow:0 2px 10px rgba(0,0,0,0.06);
			margin-bottom:15px;
		}

		.topo-canvas h4{
			margin:0 0 8px 0;
			font-weight:700;
			color:#1f4e79;
		}

		.topo-canvas .subinfo{
			font-size:14px;
			line-height:24px;
			color:#555;
		}

		.topo-canvas .explicacao{
			margin-top:12px;
			background:#f8fbff;
			border:1px solid #d9ecff;
			border-radius:8px;
			padding:12px 14px;
			font-size:14px;
			line-height:22px;
			color:#35516b;
		}

		.card-box{
			background:#ffffff;
			border-radius:10px;
			padding:15px;
			box-shadow:0 2px 10px rgba(0,0,0,0.06);
			margin-bottom:15px;
		}

		.titulo-box{
			font-size:15px;
			font-weight:700;
			color:#334e68;
			margin-bottom:10px;
		}

		.img-comparacao{
			width:100%;
			height:260px;
			object-fit:contain;
			border:1px solid #ddd;
			border-radius:8px;
			background:#fafafa;
			padding:4px;
		}

		.canvas-wrapper{
			width:100%;
			overflow:auto;
			background:#fafafa;
			border:1px solid #ddd;
			border-radius:8px;
			padding:8px;
			text-align:center;
		}

		canvas{
			display:block;
			margin:0 auto;
			background:#fff;
			border:1px solid #ddd;
			max-width:100%;
			height:auto;
			cursor:move;
			touch-action:none;
		}

		.painel-ferramentas{
			position:sticky;
			top:15px;
		}

		.preview-protese{
			max-width:100%;
			max-height:120px;
			border:1px solid #ddd;
			padding:3px;
			border-radius:4px;
			display:none;
			margin-top:8px;
			background:#fff;
		}

		.info-canvas{
			background:#fff8e8;
			border:1px solid #ffe3a6;
			border-radius:8px;
			padding:10px 12px;
			font-size:13px;
			line-height:21px;
			color:#7a5a16;
			margin-bottom:12px;
		}

		.rotulo-range{
			font-size:13px;
			font-weight:700;
			color:#5b6570;
			margin-bottom:4px;
		}

		.valor-range{
			font-size:12px;
			color:#7c8b98;
			float:right;
		}

		.grupo-botoes .btn{
			margin-bottom:10px;
		}

		.badge-canvas{
			display:inline-block;
			padding:4px 10px;
			border-radius:20px;
			font-size:12px;
			font-weight:700;
			background:#eef5ff;
			color:#2f6fad;
			margin-top:8px;
		}

		@media (max-width: 991px){
			.painel-ferramentas{
				position:relative;
				top:auto;
			}
		}

		@media (max-width: 768px){
			body{
				padding:10px;
			}

			.img-comparacao{
				height:220px;
			}
		}
	</style>
</head>
<body>

<div class="container-fluid">

	<div class="topo-canvas">
		<h4>Canvas de Ajuste Fino da Simulação</h4>

		<div class="subinfo">
			<b>Cliente:</b> <?php echo $cliente ?> &nbsp;&nbsp;
			<b>Prótese:</b> <?php echo $modelo ?>
		</div>

		<div class="explicacao">
			Este módulo permite o <b>refinamento manual da simulação</b>.  
			O profissional pode comparar a foto original com a simulação gerada pela IA e realizar ajustes visuais finais antes da apresentação ao cliente.
			<br>
			<span class="badge-canvas">Função do módulo: validação e ajuste visual final</span>
		</div>
	</div>

	<div class="row">
		<div class="col-md-4">
			<div class="card-box">
				<div class="titulo-box">Foto Original</div>
				<img src="<?php echo $url_foto_original ?>" class="img-comparacao">
			</div>
		</div>

		<div class="col-md-4">
			<div class="card-box">
				<div class="titulo-box">Simulação Gerada pela IA</div>
				<?php if($url_imagem_simulada != ""){ ?>
					<img src="<?php echo $url_imagem_simulada ?>" class="img-comparacao">
				<?php }else{ ?>
					<div class="info-canvas">Nenhuma simulação gerada por IA até o momento.</div>
				<?php } ?>
			</div>
		</div>

		<div class="col-md-4">
			<div class="card-box painel-ferramentas">
				<div class="titulo-box">Ferramentas de Ajuste</div>

				<div class="info-canvas">
					Carregue o arquivo da prótese e ajuste manualmente sobre a foto original usando posição, escala, rotação e opacidade.
				</div>

				<div class="form-group">
					<label>Arquivo da Prótese</label>
					<input type="file" id="overlayInput" class="form-control" accept="image/png,image/webp,image/jpeg">
					<img id="previewProtese" class="preview-protese">
				</div>

				<div class="form-group">
					<div class="rotulo-range">
						Tamanho
						<span class="valor-range" id="valorScale">100%</span>
					</div>
					<input type="range" id="scaleRange" class="form-control" min="10" max="300" value="100">
				</div>

				<div class="form-group">
					<div class="rotulo-range">
						Rotação
						<span class="valor-range" id="valorRotate">0°</span>
					</div>
					<input type="range" id="rotateRange" class="form-control" min="-180" max="180" value="0">
				</div>

				<div class="form-group">
					<div class="rotulo-range">
						Opacidade
						<span class="valor-range" id="valorOpacity">100%</span>
					</div>
					<input type="range" id="opacityRange" class="form-control" min="10" max="100" value="100">
				</div>

				<div class="grupo-botoes">
            	<button type="button" class="btn btn-secondary btn-block" id="btnResetar">Resetar Ajuste</button>
            	<button type="button" class="btn btn-primary btn-block" id="btnSalvar">Salvar Ajuste Final</button>
            	<a href="../../simulador" class="btn btn-default btn-block">Voltar</a>
               </div>

				<small><div id="mensagem" align="center"></div></small>
			</div>
		</div>
	</div>

	<div class="card-box">
		<div class="titulo-box">Editor Manual da Prótese</div>

		<div class="info-canvas">
			Área de ajuste fino da simulação.  
			Arraste a prótese sobre a imagem, ajuste o tamanho, a rotação e a opacidade, e então salve o resultado final.
		</div>

		<div class="canvas-wrapper">
			<canvas id="canvas"></canvas>
		</div>
	</div>

</div>

<script>
var simId = "<?php echo $id ?>";
var fotoBase = "<?php echo $url_foto_original ?>";

var canvas = document.getElementById('canvas');
var ctx = canvas.getContext('2d');

var imgBase = new Image();
var imgOverlay = new Image();

var overlayCarregado = false;
var dragging = false;
var startX = 0;
var startY = 0;
var displayScale = 1;

var overlay = {
	x: 150,
	y: 120,
	width: 220,
	height: 140,
	rotation: 0,
	opacity: 1
};

var overlayOriginal = {
	width: 220,
	height: 140
};

function atualizarLabels(){
	document.getElementById('valorScale').innerHTML = document.getElementById('scaleRange').value + '%';
	document.getElementById('valorRotate').innerHTML = document.getElementById('rotateRange').value + '°';
	document.getElementById('valorOpacity').innerHTML = document.getElementById('opacityRange').value + '%';
}

function ajustarCanvasResponsivo(){
	var larguraMaxima = document.querySelector('.canvas-wrapper').clientWidth - 20;

	if(larguraMaxima <= 0 || canvas.width <= 0){
		return;
	}

	if(canvas.width > larguraMaxima){
		displayScale = larguraMaxima / canvas.width;
		canvas.style.width = larguraMaxima + 'px';
		canvas.style.height = (canvas.height * displayScale) + 'px';
	}else{
		displayScale = 1;
		canvas.style.width = canvas.width + 'px';
		canvas.style.height = canvas.height + 'px';
	}
}

function resetarAjuste(){
	if(!overlayCarregado){
		return;
	}

	overlay.width = overlayOriginal.width;
	overlay.height = overlayOriginal.height;
	overlay.x = (canvas.width / 2) - (overlay.width / 2);
	overlay.y = (canvas.height / 3) - (overlay.height / 2);
	overlay.rotation = 0;
	overlay.opacity = 1;

	document.getElementById('scaleRange').value = 100;
	document.getElementById('rotateRange').value = 0;
	document.getElementById('opacityRange').value = 100;

	atualizarLabels();
	desenhar();
}

imgBase.onload = function(){
	canvas.width = imgBase.width;
	canvas.height = imgBase.height;
	desenhar();
	ajustarCanvasResponsivo();
};

imgBase.onerror = function(){
	document.getElementById('mensagem').innerHTML = '<span class="text-danger">Erro ao carregar a foto original</span>';
};

imgBase.src = fotoBase;

function desenhar(){
	ctx.clearRect(0, 0, canvas.width, canvas.height);
	ctx.drawImage(imgBase, 0, 0, canvas.width, canvas.height);

	if(overlayCarregado){
		ctx.save();
		ctx.globalAlpha = overlay.opacity;
		ctx.translate(overlay.x + overlay.width / 2, overlay.y + overlay.height / 2);
		ctx.rotate(overlay.rotation * Math.PI / 180);
		ctx.drawImage(imgOverlay, -overlay.width / 2, -overlay.height / 2, overlay.width, overlay.height);
		ctx.restore();
	}
}

function getCanvasCoords(clientX, clientY){
	var rect = canvas.getBoundingClientRect();

	return {
		x: (clientX - rect.left) / displayScale,
		y: (clientY - rect.top) / displayScale
	};
}

document.getElementById('overlayInput').addEventListener('change', function(e){
	var arquivo = e.target.files[0];
	if(!arquivo){
		return;
	}

	var reader = new FileReader();
	reader.onload = function(event){
		document.getElementById('previewProtese').src = event.target.result;
		document.getElementById('previewProtese').style.display = 'block';

		imgOverlay.onload = function(){
			overlayCarregado = true;

			overlay.width = imgOverlay.width;
			overlay.height = imgOverlay.height;

			if(overlay.width > 300){
				var proporcao = overlay.height / overlay.width;
				overlay.width = 300;
				overlay.height = 300 * proporcao;
			}

			overlayOriginal.width = overlay.width;
			overlayOriginal.height = overlay.height;

			overlay.x = (canvas.width / 2) - (overlay.width / 2);
			overlay.y = (canvas.height / 3) - (overlay.height / 2);
			overlay.rotation = 0;
			overlay.opacity = 1;

			document.getElementById('scaleRange').value = 100;
			document.getElementById('rotateRange').value = 0;
			document.getElementById('opacityRange').value = 100;

			atualizarLabels();
			desenhar();
		};

		imgOverlay.src = event.target.result;
	};

	reader.readAsDataURL(arquivo);
});

canvas.addEventListener('mousedown', function(e){
	if(!overlayCarregado){
		return;
	}

	var pos = getCanvasCoords(e.clientX, e.clientY);

	if(
		pos.x >= overlay.x &&
		pos.x <= overlay.x + overlay.width &&
		pos.y >= overlay.y &&
		pos.y <= overlay.y + overlay.height
	){
		dragging = true;
		startX = pos.x - overlay.x;
		startY = pos.y - overlay.y;
	}
});

canvas.addEventListener('mousemove', function(e){
	if(!dragging){
		return;
	}

	var pos = getCanvasCoords(e.clientX, e.clientY);

	overlay.x = pos.x - startX;
	overlay.y = pos.y - startY;
	desenhar();
});

canvas.addEventListener('mouseup', function(){
	dragging = false;
});

canvas.addEventListener('mouseleave', function(){
	dragging = false;
});

canvas.addEventListener('touchstart', function(e){
	if(!overlayCarregado){
		return;
	}

	var toque = e.touches[0];
	var pos = getCanvasCoords(toque.clientX, toque.clientY);

	if(
		pos.x >= overlay.x &&
		pos.x <= overlay.x + overlay.width &&
		pos.y >= overlay.y &&
		pos.y <= overlay.y + overlay.height
	){
		dragging = true;
		startX = pos.x - overlay.x;
		startY = pos.y - overlay.y;
	}

	e.preventDefault();
}, {passive:false});

canvas.addEventListener('touchmove', function(e){
	if(!dragging){
		return;
	}

	var toque = e.touches[0];
	var pos = getCanvasCoords(toque.clientX, toque.clientY);

	overlay.x = pos.x - startX;
	overlay.y = pos.y - startY;
	desenhar();

	e.preventDefault();
}, {passive:false});

canvas.addEventListener('touchend', function(){
	dragging = false;
});

document.getElementById('scaleRange').addEventListener('input', function(){
	if(!overlayCarregado){
		return;
	}

	var valor = parseInt(this.value);
	var proporcao = overlayOriginal.height / overlayOriginal.width;

	overlay.width = overlayOriginal.width * (valor / 100);
	overlay.height = overlay.width * proporcao;

	atualizarLabels();
	desenhar();
});

document.getElementById('rotateRange').addEventListener('input', function(){
	if(!overlayCarregado){
		return;
	}

	overlay.rotation = parseInt(this.value);
	atualizarLabels();
	desenhar();
});

document.getElementById('opacityRange').addEventListener('input', function(){
	if(!overlayCarregado){
		return;
	}

	overlay.opacity = parseInt(this.value) / 100;
	atualizarLabels();
	desenhar();
});

document.getElementById('btnResetar').addEventListener('click', function(){
	resetarAjuste();
});

document.getElementById('btnSalvar').addEventListener('click', function(){

	if(!overlayCarregado){
		document.getElementById('mensagem').innerHTML = '<span class="text-danger">Carregue o arquivo da prótese para ajuste manual</span>';
		return;
	}

	var imagemFinal = canvas.toDataURL('image/png');

	if(imagemFinal == ''){
		document.getElementById('mensagem').innerHTML = '<span class="text-danger">Erro ao gerar imagem do canvas</span>';
		return;
	}

	var xhr = new XMLHttpRequest();
	xhr.open('POST', 'salvar_canvas.php', true);
	xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

	xhr.onreadystatechange = function(){
		if(xhr.readyState == 4){
			if(xhr.responseText.trim() == 'Salvo com Sucesso'){
				document.getElementById('mensagem').innerHTML = '<span class="text-success">Ajuste final salvo com sucesso</span>';
			}else{
				document.getElementById('mensagem').innerHTML = '<span class="text-danger">' + xhr.responseText + '</span>';
			}
		}
	};

	xhr.send('id=' + encodeURIComponent(simId) + '&img=' + encodeURIComponent(imagemFinal));
});

window.addEventListener('resize', function(){
	ajustarCanvasResponsivo();
});

atualizarLabels();
</script>

</body>
</html>