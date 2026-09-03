<?php
require_once __DIR__ . '/_context_bootstrap.php';
// Painel - Funcionários
// Sessão/tenant é decidido centralmente em conexao.php (bootstrap do sistema).
require_once __DIR__ . '/../../conexao.php';

$pag = 'funcionarios';

//verificar se ele tem a permissão de estar nessa página
if(@$funcionarios == 'ocultar'){
    echo "<script>window.location='<?php echo $bb_painel_public_url; ?>/'</script>";
    exit();
}
?>

<div class="">
    <a class="btn btn-primary btn-flat btn-pri" href="javascript:void(0)" onclick="inserir(); return false;">
        <i class="fa fa-plus" aria-hidden="true"></i> Novo Funcionário
    </a>
</div>

<div class="bs-example widget-shadow" style="padding:15px" id="listar"></div>


<!-- Modal Inserir-->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><span id="titulo_inserir"></span></h4>
                <button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
                    <span aria-hidden="true" >&times;</span>
                </button>
            </div>

            <form id="form">
                <div class="modal-body">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="exampleInputEmail1">Nome</label>
                                <input type="text" class="form-control" id="nome" name="nome" placeholder="Nome" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="exampleInputEmail1">Email</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="exampleInputEmail1">Telefone</label>
                                <input type="text" class="form-control" id="telefone" name="telefone" placeholder="Telefone">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="exampleInputEmail1">CPF</label>
                                <input type="text" class="form-control" id="cpf" name="cpf" placeholder="CPF">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="exampleInputEmail1">Cargo</label>
                                <select class="form-control sel2" id="cargo" name="cargo" style="width:100%;">
                                    <?php
                                    $query = $pdo->query("SELECT * FROM cargos where nome != 'Administrador' ORDER BY nome asc");
                                    $res = $query->fetchAll(PDO::FETCH_ASSOC);
                                    $total_reg = @count($res);
                                    if($total_reg > 0){
                                        for($i=0; $i < $total_reg; $i++){
                                            foreach ($res[$i] as $key => $value){}
                                            echo '<option value="'.$res[$i]['nome'].'">'.$res[$i]['nome'].'</option>';
                                        }
                                    }else{
                                        echo '<option value="0">Cadastre um Cargo</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- ✅ NOVO: Credenciais MenuIA por funcionário -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
								<label for="exampleInputEmail1">Token (appkey)</label>
								<input type="text" class="form-control" id="token" name="token" placeholder="Token APP Key" value="<?php echo @$token ?>">   
							</div> 
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
								<label for="exampleInputEmail1">Instancia (authkey)</label>
								<input type="text" class="form-control" id="instancia" name="instancia" placeholder="Instância AuthKey" place value="<?php echo @$instancia ?>">   
							</div> 
                        </div>
                    </div>


                    <div class="row">

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="exampleInputEmail1">Atendimento</label>
                                <select class="form-control" name="atendimento" id="atendimento">
                                    <option value="Sim">Sim</option>
                                    <option value="Não">Não</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="exampleInputEmail1">Tipo Chave Pix</label>
                                <select class="form-control" name="tipo_chave" id="tipo_chave">
                                    <option value="">Selecionar Chave</option>
                                    <option value="CPF">CPF</option>
                                    <option value="Telefone">Telefone</option>
                                    <option value="Email">Email</option>
                                    <option value="Código">Código</option>
                                    <option value="CNPJ">CNPJ</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="exampleInputEmail1">Chave Pix</label>
                                <input type="text" class="form-control" id="chave_pix" name="chave_pix" placeholder="Chave Pix">
                            </div>
                        </div>

                    </div>


                    <div class="row">
                        <div class="col-md-9">
                            <div class="form-group">
                                <label for="exampleInputEmail1">Endereço</label>
                                <input type="text" class="form-control" id="endereco" name="endereco" placeholder="Rua X Número 1 Bairro xxx">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="exampleInputEmail1">Visualizar</label>
                                <select class="form-control" name="visualizar" id="visualizar">
                                    <option value="Sim">Todos Clientes</option>
                                    <option value="Não">Somente clientes cadastrados por ele</option>
                                </select>
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="exampleInputEmail1">Intervalo Minutos</label>
                                <input type="number" class="form-control" id="intervalo" name="intervalo" placeholder="Intervalo Horários" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="exampleInputEmail1">Comissão <small>(Se for Diferente do Padrão)</small></label>
                                <input type="number" class="form-control" id="comissao" name="comissao" placeholder="Valor R$ ou %">
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Foto</label>
                                <input class="form-control" type="file" name="foto" onChange="carregarImg();" id="foto">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div id="divImg">
                                <img src="img/perfil/sem-foto.jpg" width="80px" id="target">
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="id" id="id">

                    <br>
                    <small><div id="mensagem" align="center"></div></small>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>

        </div>
    </div>
</div>


<!-- Modal Dados-->
<div class="modal fade" id="modalDados" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="exampleModalLabel"><span id="nome_dados"></span></h4>
                <button id="btn-fechar-perfil" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
                    <span aria-hidden="true" >&times;</span>
                </button>
            </div>

            <div class="modal-body">

                <div class="row" style="border-bottom: 1px solid #cac7c7;">
                    <div class="col-md-8">
                        <span><b>Email: </b></span>
                        <span id="email_dados"></span>
                    </div>
                    <div class="col-md-4">
                        <span><b>Senha: </b></span>
                        <span id="senha_dados"></span>
                    </div>
                </div>

                <div class="row" style="border-bottom: 1px solid #cac7c7;">
                    <div class="col-md-6">
                        <span><b>CPF: </b></span>
                        <span id="cpf_dados"></span>
                    </div>
                    <div class="col-md-6">
                        <span><b>Telefone: </b></span>
                        <span id="telefone_dados"></span>
                    </div>
                </div>

                <div class="row" style="border-bottom: 1px solid #cac7c7;">
                    <div class="col-md-6">
                        <span><b>Nível: </b></span>
                        <span id="nivel_dados"></span>
                    </div>
                    <div class="col-md-6">
                        <span><b>Ativo: </b></span>
                        <span id="ativo_dados"></span>
                    </div>
                </div>

                <div class="row" style="border-bottom: 1px solid #cac7c7;">
                    <div class="col-md-6">
                        <span><b>Cadastro: </b></span>
                        <span id="data_dados"></span>
                    </div>
                    <div class="col-md-6">
                        <span><b>Atendimento: </b></span>
                        <span id="atendimento_dados"></span>
                    </div>
                </div>

                <div class="row" style="border-bottom: 1px solid #cac7c7;">
                    <div class="col-md-6">
                        <span><b>Tipo Chave: </b></span>
                        <span id="tipo_chave_dados"></span>
                    </div>
                    <div class="col-md-6">
                        <span><b>Chave Pix: </b></span>
                        <span id="chave_pix_dados"></span>
                    </div>
                </div>

                <!-- ✅ NOVO: mostra credenciais -->
                <div class="row" style="border-bottom: 1px solid #cac7c7;">
                    <div class="col-md-6">
                        <span><b>AuthKey (Token): </b></span>
                        <span id="token_dados"></span>
                    </div>
                    <div class="col-md-6">
                        <span><b>AppKey (Instância): </b></span>
                        <span id="instancia_dados"></span>
                    </div>
                </div>

                <div class="row" style="border-bottom: 1px solid #cac7c7;">
                    <div class="col-md-12">
                        <span><b>Endereço: </b></span>
                        <span id="endereco_dados"></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12" align="center">
                        <img width="250px" id="target_mostrar">
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>


<!-- Modal Horarios-->
<div class="modal fade" id="modalHorarios" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="exampleModalLabel"><span id="nome_horarios"></span></h4>
                <button id="btn-fechar-horarios" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
                    <span aria-hidden="true" >&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <form id="form-horarios">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="exampleInputEmail1">Horário</label>
                                <input type="time" class="form-control" id="horario" name="horario" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary" style="margin-top:20px">Salvar</button>
                        </div>

                        <input type="hidden" name="id" id="id_horarios">
                    </div>
                </form>

                <hr>
                <div class="" id="listar-horarios"></div>

                <br>
                <small><div id="mensagem-horarios"></div></small>
            </div>

        </div>
    </div>
</div>


<!-- Modal Dias-->
<div class="modal fade" id="modalDias" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="width:80%" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="exampleModalLabel"><span id="nome_dias"></span></h4>
                <button id="btn-fechar-dias" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
                    <span aria-hidden="true" >&times;</span>
                </button>
            </div>

            <div class="modal-body">

                <form id="form-dias">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="exampleInputEmail1">Dia</label>
                                <select class="form-control" id="dias" name="dias" required>
                                    <option value="Segunda-Feira">Segunda-Feira</option>
                                    <option value="Terça-Feira">Terça-Feira</option>
                                    <option value="Quarta-Feira">Quarta-Feira</option>
                                    <option value="Quinta-Feira">Quinta-Feira</option>
                                    <option value="Sexta-Feira">Sexta-Feira</option>
                                    <option value="Sábado">Sábado</option>
                                    <option value="Domingo">Domingo</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4" align="center">
                            <label for="exampleInputEmail1">(Início) Jornada de Trabalho (Final)</label>
                            <div class="row" style="margin-top: 2px">
                                <div class="col-md-6">
                                    <input type="time" name="inicio" class="form-control" id="inicio" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="time" name="final" class="form-control" id="final" required>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4" align="center">
                            <label for="exampleInputEmail1">(Início) Intervalo de Almoço (Final)</label>
                            <div class="row" style="margin-top: 2px">
                                <div class="col-md-6">
                                    <input type="time" name="inicio_almoco" class="form-control" id="inicio_almoco">
                                </div>
                                <div class="col-md-6">
                                    <input type="time" name="final_almoco" class="form-control" id="final_almoco">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary" style="margin-top:22px">Salvar</button>
                        </div>

                        <input type="hidden" name="id" id="id_dias" value="<?php echo $id_usuario ?>">
                        <input type="hidden" name="id_d" id="id_d">
                    </div>
                </form>

                <small><div id="mensagem-dias"></div></small>

                <big>
                    <div class="bs-example widget-shadow" style="padding:15px" id="listar-dias"></div>
                </big>
            </div>

        </div>
    </div>
</div>


<!-- Modal Servicos-->
<div class="modal fade" id="modalServicos" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="exampleModalLabel"><span id="nome_servico"></span></h4>
                <button id="btn-fechar-servico" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
                    <span aria-hidden="true" >&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <form id="form-servico">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="exampleInputEmail1">Serviço</label>
                                <select class="form-control sel3" id="servico" name="servico" style="width:100%;" required>
                                    <?php
                                    // Lista de serviços respeitando o contexto do marketplace
                                    // /loja/{slug}/  => dados da loja (mesmo que o nível seja Administrador)
                                    // /sistema/...   => dados do master
                                    $req_path_serv = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
                                    $ref_path_serv = '';
$em_loja_serv = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $req_path_serv)
                                                    || (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $ref_path_serv);

                                    $is_admin = false;
                                    $role_keys = ['nivel', 'nivel_usuario', 'perfil', 'cargo', 'tipo', 'tipo_usuario', 'role'];
                                    foreach($role_keys as $rk){
                                        if(isset($_SESSION[$rk]) && preg_match('/admin|administrador|master|root/i', (string)$_SESSION[$rk])){
                                            $is_admin = true;
                                            break;
                                        }
                                    }

                                    // Só trata como admin master fora de /loja/{slug}/
                                    $is_admin_master = ($is_admin && !$em_loja_serv);

                                    if (!function_exists('bb_func_col_info')) {
                                        function bb_func_col_info(PDO $pdo, string $table, string $col): ?array {
                                            $sql = "SELECT DATA_TYPE, IS_NULLABLE
                                                      FROM INFORMATION_SCHEMA.COLUMNS
                                                     WHERE TABLE_SCHEMA = DATABASE()
                                                       AND TABLE_NAME = :t
                                                       AND COLUMN_NAME = :c
                                                     LIMIT 1";
                                            $st = $pdo->prepare($sql);
                                            $st->execute([':t' => $table, ':c' => $col]);
                                            $row = $st->fetch(PDO::FETCH_ASSOC);
                                            return $row ?: null;
                                        }
                                    }

                                    if (!function_exists('bb_func_col_exists')) {
                                        function bb_func_col_exists(PDO $pdo, string $table, string $col): bool {
                                            return bb_func_col_info($pdo, $table, $col) !== null;
                                        }
                                    }

                                    if (!function_exists('bb_func_first_existing_col')) {
                                        function bb_func_first_existing_col(PDO $pdo, string $table, array $candidatos): string {
                                            foreach ($candidatos as $col) {
                                                if (bb_func_col_exists($pdo, $table, $col)) {
                                                    return $col;
                                                }
                                            }
                                            return '';
                                        }
                                    }

                                    if (!function_exists('bb_func_is_numeric_type')) {
                                        function bb_func_is_numeric_type(string $dataType): bool {
                                            $dataType = strtolower($dataType);
                                            return in_array($dataType, ['int','integer','tinyint','smallint','mediumint','bigint','decimal','numeric','float','double'], true);
                                        }
                                    }

                                    if (!function_exists('bb_func_pick_tenant_value')) {
                                        function bb_func_pick_tenant_value(PDO $pdo, string $table, string $tenantCol) {
                                            if ($tenantCol === '') return null;

                                            $info = bb_func_col_info($pdo, $table, $tenantCol);
                                            $dt = $info ? strtolower((string)$info['DATA_TYPE']) : '';

                                            $numKeys = ['id_loja','loja_id','id_empresa','empresa_id','id_usuario','usuario_id','empresa','loja','usuario','tenant_id'];
                                            $strKeys = ['tenant_slug','slug','tenant','loja','empresa'];

                                            if (bb_func_is_numeric_type($dt)) {
                                                foreach ($numKeys as $k) {
                                                    if (isset($_SESSION[$k]) && (string)$_SESSION[$k] !== '' && (int)$_SESSION[$k] > 0) return (int)$_SESSION[$k];
                                                    if (isset($_GET[$k]) && (string)$_GET[$k] !== '' && (int)$_GET[$k] > 0) return (int)$_GET[$k];
                                                    if (isset($_POST[$k]) && (string)$_POST[$k] !== '' && (int)$_POST[$k] > 0) return (int)$_POST[$k];
                                                }
                                                return null;
                                            }

                                            foreach ($strKeys as $k) {
                                                if (isset($_SESSION[$k]) && trim((string)$_SESSION[$k]) !== '') return trim((string)$_SESSION[$k]);
                                                if (isset($_GET[$k]) && trim((string)$_GET[$k]) !== '') return trim((string)$_GET[$k]);
                                                if (isset($_POST[$k]) && trim((string)$_POST[$k]) !== '') return trim((string)$_POST[$k]);
                                            }

                                            return null;
                                        }
                                    }

                                    $res = [];

                                    if ($is_admin_master) {
                                        $query = $pdo->query("SELECT * FROM servicos ORDER BY nome asc");
                                        $res = $query->fetchAll(PDO::FETCH_ASSOC);
                                    } else {
                                        $tenantCandidates = ['id_loja','loja','id_empresa','empresa','id_usuario','usuario','tenant','tenant_id','tenant_slug'];
                                        $tenantColServ = bb_func_first_existing_col($pdo, 'servicos', $tenantCandidates);
                                        $tenantValServ = ($tenantColServ !== '') ? bb_func_pick_tenant_value($pdo, 'servicos', $tenantColServ) : null;

                                        if ($tenantColServ !== '' && $tenantValServ !== null && $tenantValServ !== '') {
                                            $sql = "SELECT * FROM servicos WHERE {$tenantColServ} = :tenant ORDER BY nome asc";
                                            $stmt = $pdo->prepare($sql);
                                            $stmt->bindValue(':tenant', $tenantValServ, is_int($tenantValServ) ? PDO::PARAM_INT : PDO::PARAM_STR);
                                            $stmt->execute();
                                            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        } else {
                                            // Mantém o padrão do sistema: sem coluna tenant, usa o banco já conectado no contexto atual
                                            $query = $pdo->query("SELECT * FROM servicos ORDER BY nome asc");
                                            $res = $query->fetchAll(PDO::FETCH_ASSOC);
                                        }
                                    }

                                    $total_reg = @count($res);
                                    if($total_reg > 0){
                                        for($i=0; $i < $total_reg; $i++){
                                            foreach ($res[$i] as $key => $value){}
                                            echo '<option value="'.$res[$i]['id'].'">'.$res[$i]['nome'].'</option>';
                                        }
                                    }

                            ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary" style="margin-top:20px">Salvar</button>
                        </div>

                        <input type="hidden" name="id" id="id_servico">
                    </div>
                </form>

                <hr>
                <div class="" id="listar-servicos"></div>

                <br>
                <small><div id="mensagem-servicos"></div></small>
            </div>

        </div>
    </div>
</div>


<script type="text/javascript">var pag = "<?=$pag?>"</script>
<script src="js/ajax.js"></script>

<script type="text/javascript">
    $(document).ready(function() {
        $('.sel2').select2({ dropdownParent: $('#modalForm') });
        $('.sel3').select2({ dropdownParent: $('#modalServicos') });
    });
</script>

<script type="text/javascript">
    function carregarImg() {
        var target = document.getElementById('target');
        var file = document.querySelector("#foto").files[0];
        var reader = new FileReader();

        reader.onloadend = function () {
            target.src = reader.result;
        };

        if (file) {
            reader.readAsDataURL(file);
        } else {
            target.src = "";
        }
    }
</script>


<script type="text/javascript">
    // Mantém a lógica: muda status via mudar-status.php e fica na página (sem redirect)
    function ativar(id, acao){
        $.ajax({
            url: 'paginas/' + pag + '/mudar-status.php',
            method: 'POST',
            dataType: 'text',
            cache: false,
            data: { id: id, acao: acao },
            success: function(){
                // função listar() é do js/ajax.js
                if(typeof listar === 'function'){ listar(); }
            }
        });
    }
</script>


<script type="text/javascript">
    // HORÁRIOS
    $("#form-horarios").submit(function (event) {
        event.preventDefault();

        var func = $("#id_horarios").val();
        var formData = new FormData(this);

        // garante o id do funcionário
        formData.set('id', func);

        $.ajax({
            url: 'paginas/' + pag + "/inserir-horario.php",
            type: 'POST',
            data: formData,
            success: function (mensagem) {
                $('#mensagem-horarios').text('').removeClass();
                if (mensagem.trim() == "Salvo com Sucesso") {
                    $('#horario').val('');
                    listarHorarios(func);
                } else {
                    $('#mensagem-horarios').addClass('text-danger').text(mensagem);
                }
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });

    function listarHorarios(func){
        $.ajax({
            url: 'paginas/' + pag + "/listar-horarios.php",
            method: 'POST',
            data: {func: func},
            dataType: "html",
            success:function(result){
                $("#listar-horarios").html(result);
                $('#mensagem-horarios').text('');
            }
        });
    }
</script>


<script type="text/javascript">
    // DIAS
    $("#form-dias").submit(function (event) {
        event.preventDefault();

        var func = $("#id_dias").val();
        var formData = new FormData(this);

        // garante o id do funcionário
        formData.set('id', func);

        $.ajax({
            url: 'paginas/' + pag + "/inserir-dias.php",
            type: 'POST',
            data: formData,
            success: function (mensagem) {
                $('#mensagem-dias').text('').removeClass();
                if (mensagem.trim() == "Salvo com Sucesso") {
                    $("#id_d").val('');
                    listarDias(func);
                } else {
                    $('#mensagem-dias').addClass('text-danger').text(mensagem);
                }
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });

    function listarDias(func){
        $.ajax({
            url: 'paginas/' + pag + "/listar-dias.php",
            method: 'POST',
            data: {func: func},
            dataType: "html",
            success:function(result){
                $("#listar-dias").html(result);
                $('#mensagem-dias').text('');
            }
        });
    }
</script>

<script type="text/javascript">
    // SERVIÇOS (mantém lógica, só garante envio correto do ID do serviço)
    $("#form-servico").submit(function (event) {
        event.preventDefault();

        var func = $("#id_servico").val();      // ID do funcionário (hidden)
        var servico = $("#servico").val();      // ID do serviço (select)

        // validações mínimas ANTES de enviar (porque usamos preventDefault e não roda "required")
        if (!func || parseInt(func, 10) <= 0) {
            $('#mensagem-servicos').addClass('text-danger').text('Funcionário inválido!');
            return;
        }

        if (!servico || parseInt(servico, 10) <= 0) {
            $('#mensagem-servicos').addClass('text-danger').text('Serviço inválido!');
            return;
        }

        var formData = new FormData(this);

        // garante os dois campos SEM depender do select2 / DOM
        formData.set('id', func);
        formData.set('servico', servico);

        $.ajax({
            url: 'paginas/' + pag + "/inserir-servico.php",
            type: 'POST',
            data: formData,
            success: function (mensagem) {
                $('#mensagem-servicos').text('').removeClass();
                if (mensagem.trim() == "Salvo com Sucesso") {
                    listarServicos(func);
                } else {
                    $('#mensagem-servicos').addClass('text-danger').text(mensagem);
                }
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });

    function listarServicos(func){
        $.ajax({
            url: 'paginas/' + pag + "/listar-servicos.php",
            method: 'POST',
            data: {func: func},
            dataType: "html",
            success:function(result){
                $("#listar-servicos").html(result);
                $('#mensagem-servicos').text('');
            }
        });
    }
</script>

