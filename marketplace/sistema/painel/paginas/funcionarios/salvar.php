<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'usuarios';

/**
 * ✅ TENANT HELPER (sem redeclare)
 * caminho: /public_html/api/tenant.php
 */
$bbRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$bbTenantHelper = $bbRoot . '/api/tenant.php';
if (is_file($bbTenantHelper)) {
    require_once($bbTenantHelper);
}

$tenant = '';
if (function_exists('tenant_slug')) {
    $tenant = tenant_slug(); // GET / SESSION / URI
}

/**
 * ✅ Verifica se coluna existe (para não quebrar bancos antigos dos tenants)
 */
if (!function_exists('bb_has_column')) {
    function bb_has_column(PDO $pdo, string $table, string $column): bool {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :col");
            $stmt->bindValue(':col', $column);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? true : false;
        } catch (Exception $e) {
            return false;
        }
    }
}

$has_token = bb_has_column($pdo, $tabela, 'token');
$has_instancia = bb_has_column($pdo, $tabela, 'instancia');

$id = $_POST['id'] ?? '';
$nome = $_POST['nome'] ?? '';
$email = $_POST['email'] ?? '';
$telefone = $_POST['telefone'] ?? '';
$cpf = $_POST['cpf'] ?? '';
$cargo = $_POST['cargo'] ?? '';
$endereco = $_POST['endereco'] ?? '';
$atendimento = $_POST['atendimento'] ?? '';
$tipo_chave = $_POST['tipo_chave'] ?? '';
$chave_pix = $_POST['chave_pix'] ?? '';
$senha = '123';
$senha_crip = password_hash($senha, PASSWORD_DEFAULT);
$intervalo = $_POST['intervalo'] ?? '';
$comissao = $_POST['comissao'] ?? '';
$visualizar = isset($_POST['visualizar']) ? $_POST['visualizar'] : '';

// ✅ NOVO (MenuIA por funcionário) - mantém, mas só salva se existir coluna no banco
$token = $_POST['token'] ?? '';
$instancia = $_POST['instancia'] ?? '';

if ($cargo == "0") {
    echo 'Cadastre um Cargo para o Usuário';
    exit();
}

// ==============================
// VALIDAR EMAIL (mesma lógica)
// ==============================
$stmt = $pdo->prepare("SELECT id FROM {$tabela} WHERE email = :email LIMIT 1");
$stmt->bindValue(':email', $email);
$stmt->execute();
$resEmail = $stmt->fetch(PDO::FETCH_ASSOC);

if ($resEmail && $id != $resEmail['id']) {
    echo 'Email já Cadastrado, escolha outro!!';
    exit();
}

// ==============================
// VALIDAR CPF (mesma lógica)
// ==============================
$stmt = $pdo->prepare("SELECT id FROM {$tabela} WHERE cpf = :cpf LIMIT 1");
$stmt->bindValue(':cpf', $cpf);
$stmt->execute();
$resCpf = $stmt->fetch(PDO::FETCH_ASSOC);

if ($resCpf && $id != $resCpf['id']) {
    echo 'CPF já Cadastrado, escolha outro!!';
    exit();
}

// ==============================
// VALIDAR TROCA DA FOTO (mesma lógica)
// ==============================
$foto = 'sem-foto.jpg';
if ($id != "") {
    $stmt = $pdo->prepare("SELECT foto FROM {$tabela} WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
    $stmt->execute();
    $dadosFoto = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($dadosFoto && isset($dadosFoto['foto']) && $dadosFoto['foto'] != "") {
        $foto = $dadosFoto['foto'];
    }
}

/**
 * ✅ Diretório de fotos (isolado por tenant quando existir)
 * - Tenant: /public_html/sistema/tenants/<slug>/painel_img/perfil
 * - Fallback antigo: /public_html/sistema/painel/img/perfil
 */
$dir_perfil = __DIR__ . '/../../img/perfil'; // padrão antigo (painel/img/perfil)

if ($tenant != '' && function_exists('tenant_painel_img_dir')) {
    $dir_perfil = rtrim(tenant_painel_img_dir($tenant), '/\\') . '/perfil';
} elseif ($tenant != '' && function_exists('tenant_img_dir')) {
    // fallback caso seu helper não use painel_img
    $dir_perfil = rtrim(tenant_img_dir($tenant), '/\\') . '/perfil';
}

if (!is_dir($dir_perfil)) {
    @mkdir($dir_perfil, 0755, true);
}

// ==============================
// SCRIPT PARA SUBIR FOTO NO SERVIDOR (mesma lógica)
// ==============================
$nome_img = date('d-m-Y H:i:s') . '-' . (@$_FILES['foto']['name'] ?? '');
$nome_img = preg_replace('/[ :]+/', '-', $nome_img);
$nome_img = basename($nome_img); // evita path traversal

$caminho = rtrim($dir_perfil, '/\\') . '/' . $nome_img;
$imagem_temp = @$_FILES['foto']['tmp_name'];

if (@$_FILES['foto']['name'] != "") {
    $ext = strtolower(pathinfo($nome_img, PATHINFO_EXTENSION));
    if ($ext == 'png' or $ext == 'jpg' or $ext == 'jpeg' or $ext == 'gif' or $ext == 'webp') {

        // EXCLUO A FOTO ANTERIOR (mesma lógica)
        if ($foto != "sem-foto.jpg") {
            @unlink(rtrim($dir_perfil, '/\\') . '/' . basename($foto));
        }

        $foto = $nome_img;

        move_uploaded_file($imagem_temp, $caminho);
    } else {
        echo 'Extensão de Imagem não permitida!';
        exit();
    }
}

// ==============================
// INSERT OU UPDATE (mesma lógica)
// ==============================
$extra_sql = "";
if ($has_token) {
    $extra_sql .= ", token = :token";
}
if ($has_instancia) {
    $extra_sql .= ", instancia = :instancia";
}

if ($id == "") {

    $query = $pdo->prepare("
        INSERT INTO {$tabela} SET 
            nome = :nome, 
            email = :email, 
            cpf = :cpf, 
            senha = '', 
            senha_crip = :senha_crip, 
            nivel = :cargo, 
            data = curDate(), 
            ativo = 'Sim', 
            telefone = :telefone, 
            endereco = :endereco, 
            foto = :foto, 
            atendimento = :atendimento, 
            tipo_chave = :tipo_chave, 
            chave_pix = :chave_pix, 
            intervalo = :intervalo, 
            comissao = :comissao, 
            visualizar = :visualizar
            {$extra_sql}
    ");
    $query->bindValue(":senha_crip", $senha_crip);

} else {

    $query = $pdo->prepare("
        UPDATE {$tabela} SET 
            nome = :nome, 
            email = :email, 
            cpf = :cpf, 
            nivel = :cargo, 
            telefone = :telefone, 
            endereco = :endereco, 
            foto = :foto, 
            atendimento = :atendimento, 
            tipo_chave = :tipo_chave, 
            chave_pix = :chave_pix, 
            intervalo = :intervalo, 
            comissao = :comissao, 
            visualizar = :visualizar
            {$extra_sql}
        WHERE id = :id
    ");
    $query->bindValue(":id", (int)$id, PDO::PARAM_INT);
}

$query->bindValue(":nome", $nome);
$query->bindValue(":email", $email);
$query->bindValue(":cpf", $cpf);
$query->bindValue(":cargo", $cargo);
$query->bindValue(":telefone", $telefone);
$query->bindValue(":endereco", $endereco);
$query->bindValue(":foto", $foto);
$query->bindValue(":atendimento", $atendimento);
$query->bindValue(":tipo_chave", $tipo_chave);
$query->bindValue(":chave_pix", $chave_pix);
$query->bindValue(":intervalo", $intervalo);
$query->bindValue(":comissao", $comissao);
$query->bindValue(":visualizar", $visualizar);

// ✅ só faz bind se a coluna existir nesse banco
if ($has_token) {
    $query->bindValue(":token", $token);
}
if ($has_instancia) {
    $query->bindValue(":instancia", $instancia);
}

$query->execute();

echo 'Salvo com Sucesso';
?>