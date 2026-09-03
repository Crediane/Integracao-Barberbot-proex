<?php
/**
 * SCRIPT DE DIAGNÓSTICO - BANNERS PROTESE.PHP
 * 
 * Salve este arquivo como diagnostico.php na raiz do site
 * Acesse: https://jacycabeleireiro.com/diagnostico.php
 */

header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Diagnóstico - Banners</title>';
echo '<style>body{font-family:monospace;padding:20px;background:#000;color:#0f0;}';
echo '.ok{color:#0f0;} .erro{color:#f00;} .aviso{color:#ff0;} h2{color:#0ff;}</style></head><body>';

echo '<h1>🔍 DIAGNÓSTICO - BANNERS PROTESE.PHP</h1>';
echo '<p>Data/Hora: ' . date('d/m/Y H:i:s') . '</p><hr>';

// === 1. CONFIGURAÇÕES PHP ===
echo '<h2>1️⃣ Configurações PHP</h2>';
echo '<pre>';
echo 'PHP Version: ' . PHP_VERSION . "\n";
echo 'Document Root: ' . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo 'Script Filename: ' . __FILE__ . "\n";
echo 'Error Reporting: ' . error_reporting() . "\n";
echo 'Display Errors: ' . ini_get('display_errors') . "\n";
echo '</pre><hr>';

// === 2. BANCO DE DADOS ===
echo '<h2>2️⃣ Banco de Dados</h2>';
$pdo = null;
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/conexao.php';
    echo '<p class="ok">✅ Conexão com banco OK</p>';
    
    // Verificar tabelas
    $tables = ['site_banners_data', 'site_banners', 'arquivos'];
    foreach ($tables as $t) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$t'");
        if ($stmt->fetch()) {
            echo '<p class="ok">✅ Tabela <strong>' . $t . '</strong> existe</p>';
            
            // Contar registros
            $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            echo '<p>   └─ Total de registros: ' . $count . '</p>';
            
            // Mostrar primeiros 3 registros
            if ($t === 'site_banners_data' || $t === 'site_banners') {
                $rows = $pdo->query("SELECT * FROM `$t` LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
                echo '<pre>';
                foreach ($rows as $idx => $r) {
                    echo "\n   Registro #" . ($idx+1) . ":\n";
                    foreach ($r as $col => $val) {
                        echo "      $col: " . var_export($val, true) . "\n";
                    }
                }
                echo '</pre>';
            }
        } else {
            echo '<p class="aviso">⚠️ Tabela <strong>' . $t . '</strong> NÃO existe</p>';
        }
    }
} catch (Exception $e) {
    echo '<p class="erro">❌ Erro ao conectar ao banco: ' . $e->getMessage() . '</p>';
}
echo '<hr>';

// === 3. ESTRUTURA DE DIRETÓRIOS ===
echo '<h2>3️⃣ Estrutura de Diretórios</h2>';
$dirs = [
    '/sistema/painel/paginas/site/uploads_banners',
    '/sistema/painel/paginas/site/uploads_carrossel',
    '/sistema/public/uploads/site',
    '/sistema/img',
    '/sistema/painel',
];

foreach ($dirs as $d) {
    $full = $_SERVER['DOCUMENT_ROOT'] . $d;
    if (is_dir($full)) {
        echo '<p class="ok">✅ <strong>' . $d . '</strong> existe</p>';
        
        // Listar arquivos
        $files = @scandir($full);
        if ($files) {
            $imgs = array_filter($files, function($f) use ($full) {
                return is_file($full . '/' . $f) && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $f);
            });
            
            echo '<p>   └─ Imagens encontradas: ' . count($imgs) . '</p>';
            
            if (count($imgs) > 0) {
                echo '<pre>   Arquivos:\n';
                foreach (array_slice($imgs, 0, 10) as $img) {
                    $size = filesize($full . '/' . $img);
                    $perm = substr(sprintf('%o', fileperms($full . '/' . $img)), -4);
                    echo "      • $img (". number_format($size/1024, 1) ."KB, perm: $perm)\n";
                }
                if (count($imgs) > 10) echo "      ... e mais " . (count($imgs) - 10) . " arquivo(s)\n";
                echo '</pre>';
            }
        }
        
        // Verificar permissões
        $perms = substr(sprintf('%o', fileperms($full)), -4);
        echo '<p>   └─ Permissões: ' . $perms;
        if ($perms >= '0755') echo ' <span class="ok">✅ OK</span>';
        else echo ' <span class="erro">❌ PROBLEMA (deve ser 755)</span>';
        echo '</p>';
        
    } else {
        echo '<p class="erro">❌ <strong>' . $d . '</strong> NÃO existe</p>';
    }
}
echo '<hr>';

// === 4. TESTE DE RESOLUÇÃO DE CAMINHOS ===
echo '<h2>4️⃣ Teste de Resolução de Caminhos</h2>';

if ($pdo) {
    // Buscar um registro real do banco
    $stmt = $pdo->query("SELECT arquivo, src, caminho FROM site_banners_data LIMIT 1");
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($banner) {
        $src = $banner['arquivo'] ?? $banner['src'] ?? $banner['caminho'] ?? '';
        echo '<p>Caminho do banco: <strong>' . htmlspecialchars($src) . '</strong></p>';
        
        // Testar vários candidatos
        $cands = [
            '/sistema/painel/paginas/site/uploads_banners/' . ltrim($src, '/'),
            '/' . ltrim($src, '/'),
            '/sistema/public/uploads/site/' . basename($src),
            '/sistema/img/' . basename($src),
        ];
        
        echo '<p>Testando candidatos:</p><pre>';
        $encontrado = false;
        foreach ($cands as $c) {
            $abs = $_SERVER['DOCUMENT_ROOT'] . $c;
            $existe = is_file($abs);
            $status = $existe ? '<span class="ok">✅ ENCONTRADO</span>' : '<span class="erro">❌ não existe</span>';
            echo "   $c\n      $status\n";
            if ($existe && !$encontrado) {
                $encontrado = true;
                echo "      └─ Este será usado!\n";
                echo "      └─ URL: https://jacycabeleireiro.com$c\n";
            }
        }
        echo '</pre>';
        
        if (!$encontrado) {
            echo '<p class="erro">❌ PROBLEMA: Nenhum caminho válido encontrado!</p>';
            echo '<p class="aviso">⚠️ SOLUÇÃO: Mova as imagens para um dos diretórios testados acima.</p>';
        }
    } else {
        echo '<p class="aviso">⚠️ Nenhum banner encontrado no banco para testar</p>';
    }
} else {
    echo '<p class="erro">❌ Sem conexão com banco, não é possível testar</p>';
}
echo '<hr>';

// === 5. TESTE DE ACESSO WEB ===
echo '<h2>5️⃣ Teste de Acesso Web</h2>';
echo '<p>Testando se conseguimos acessar as imagens via HTTP...</p>';

// Pegar uma imagem real do diretório prioritário
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/sistema/painel/paginas/site/uploads_banners';
if (is_dir($uploadDir)) {
    $files = @scandir($uploadDir);
    $imgs = array_filter($files, function($f) use ($uploadDir) {
        return is_file($uploadDir . '/' . $f) && preg_match('/\.(jpg|jpeg|png|webp)$/i', $f);
    });
    
    if (count($imgs) > 0) {
        $img = reset($imgs);
        $url = '/sistema/painel/paginas/site/uploads_banners/' . $img;
        $fullUrl = 'https://jacycabeleireiro.com' . $url;
        
        echo '<p>Imagem de teste: <strong>' . $img . '</strong></p>';
        echo '<p>URL: <a href="' . $fullUrl . '" target="_blank" style="color:#0ff;">' . $fullUrl . '</a></p>';
        echo '<p>Clique no link acima. A imagem deve abrir.</p>';
        echo '<img src="' . $url . '" alt="Teste" style="max-width:300px;border:2px solid #0f0;margin:10px 0;" onerror="this.style.border=\'2px solid #f00\'; this.alt=\'❌ ERRO AO CARREGAR\';">';
    } else {
        echo '<p class="erro">❌ Nenhuma imagem encontrada em uploads_banners</p>';
    }
} else {
    echo '<p class="erro">❌ Diretório uploads_banners não existe</p>';
}

echo '<hr>';

// === 6. LOGS DO ERROR.LOG ===
echo '<h2>6️⃣ Últimas Linhas do Error Log</h2>';
$errorLog = '/var/log/apache2/error.log';
if (file_exists($errorLog) && is_readable($errorLog)) {
    $lines = @file($errorLog);
    if ($lines) {
        $last = array_slice($lines, -50);
        echo '<pre style="max-height:400px;overflow:auto;background:#111;padding:10px;">';
        foreach ($last as $line) {
            if (stripos($line, 'resolve_media') !== false) {
                echo '<span class="ok">' . htmlspecialchars($line) . '</span>';
            } elseif (stripos($line, 'error') !== false || stripos($line, 'warning') !== false) {
                echo '<span class="erro">' . htmlspecialchars($line) . '</span>';
            } else {
                echo htmlspecialchars($line);
            }
        }
        echo '</pre>';
    } else {
        echo '<p class="aviso">⚠️ Não foi possível ler o error.log</p>';
    }
} else {
    echo '<p class="aviso">⚠️ Error log não encontrado ou sem permissão de leitura</p>';
}

echo '<hr>';
echo '<h2>✅ DIAGNÓSTICO COMPLETO</h2>';
echo '<p>Envie este relatório para análise do problema.</p>';
echo '</body></html>';
?>