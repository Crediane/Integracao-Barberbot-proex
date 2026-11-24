<?php
// 1. IMPORTA A CHAVE DA GROQ E CONFIGURAÇÕES
require_once 'config.php';

// 2. FUNÇÃO: TRANSCREVER ÁUDIO NA GROQ
function transcreverAudioGroq($caminho_arquivo) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.groq.com/openai/v1/audio/transcriptions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'file' => new CURLFile($caminho_arquivo),
        'model' => 'whisper-large-v3',
        'language' => 'pt'
    ]);
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . GROQ_API_KEY
    ]);
    
    $resposta = curl_exec($ch);
    $erro = curl_error($ch);
    curl_close($ch);
    
    file_put_contents('log_groq.txt', "Erro: " . $erro . "\nResposta: " . $resposta . "\n\n", FILE_APPEND);
    
    $dados = json_decode($resposta, true);
    return isset($dados['text']) ? trim($dados['text']) : '';
}

// 3. FUNÇÃO: IR BUSCAR O BASE64 À EVOLUTION API À FORÇA (CORRIGIDA)
function buscarAudioDaEvolution($dados_mensagem) {
    $ch = curl_init('http://127.0.0.1:8080/chat/getBase64FromMediaMessage/MeuBotPHP');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    
    // Passamos a variável inteira
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'message' => $dados_mensagem
    ]));
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: BarberBot123", 
        "Content-Type: application/json"
    ]);
    
    $resposta = curl_exec($ch);
    $erro_curl = curl_error($ch);
    curl_close($ch);
    
    file_put_contents('log_base64.txt', "Erro cURL: " . $erro_curl . "\nResposta API: " . $resposta);
    
    $json = json_decode($resposta, true);
    return $json['base64'] ?? '';
}

$json_recebido = file_get_contents('php://input');
$dados = json_decode($json_recebido, true);

// --- INÍCIO DO BLOQUEIO ANTI-LOOP E FILTROS ---
// 1. Ignora eventos que não são mensagens novas (ex: status de lida, entregue)
if (isset($dados['event']) && $dados['event'] !== 'messages.upsert') {
    http_response_code(200);
    exit;
}

// 2. Ignora requisições vazias ou sem o objeto de mensagem
if (!isset($dados['data']['message'])) {
    http_response_code(200);
    exit;
}

// 3. Ignora mensagens enviadas pelo próprio bot ou pelo seu próprio número (fromMe)
if (isset($dados['data']['key']['fromMe']) && $dados['data']['key']['fromMe'] == true) {
    http_response_code(200);
    exit;
}
// --- FIM DO BLOQUEIO ANTI-LOOP ---

// Atualiza o log visual do WhatsApp
file_put_contents('log_whatsapp.txt', print_r($dados, true));

if (isset($dados['data']['key']['remoteJid'])) {
    $fromMe = $dados['data']['key']['fromMe'];
    $numero_remetente = $dados['data']['key']['remoteJid'];

    // --- NOVO FILTRO ANTI-BOTS E GRUPOS ---
$apenas_numeros = preg_replace('/[^0-9]/', '', $numero_remetente);
// Ignora grupos (@g.us) e números de serviços automáticos (com menos de 10 dígitos)
if (strpos($numero_remetente, '@g.us') !== false || strlen($apenas_numeros) < 10) {
    http_response_code(200);
    exit;
}
// --------------------------------------
    
    // CAPTURA DO TIPO DE MENSAGEM
    $tipo_mensagem = $dados['data']['messageType'] ?? '';
    if (isset($dados['data']['message']['audioMessage'])) {
        $tipo_mensagem = 'audioMessage';
    }
    
    $texto_recebido = trim($dados['data']['message']['conversation'] ?? '');
    
    // 4. INTERCEPTAÇÃO ATIVA DO ÁUDIO
    if ($tipo_mensagem === 'audioMessage') {
        $base64_audio = buscarAudioDaEvolution($dados['data']);
        
        if (!empty($base64_audio)) {
            $arquivo_temp = 'temp_' . time() . '_' . rand(1000, 9999) . '.ogg';
            file_put_contents($arquivo_temp, base64_decode($base64_audio));
            
            $texto_transcrito = transcreverAudioGroq($arquivo_temp);
            if (!empty($texto_transcrito)) {
                $texto_recebido = $texto_transcrito;
            }
            
            if (file_exists($arquivo_temp)) {
                unlink($arquivo_temp);
            }
        }
    }

    // 5. MÁQUINA DE ESTADOS ORIGINAL DO BARBERBOT
    if (!$fromMe && $texto_recebido !== '') {
        $numero_limpo = str_replace('@s.whatsapp.net', '', $numero_remetente);
        $arquivo_sessao = 'sessao_' . $numero_limpo . '.json';

        $etapa_atual = 0;
        $cliente_id = null;
        $servico_escolhido = null;
        $profissional_escolhido = null;
        $data_escolhida = null;
        $agendamentos_cancelar = [];
        $datas_disponiveis = []; 
        $horas_disponiveis = []; 
        $encerrar_sessao = false; 
        
        $enviar_como_media = false; 
        $caminho_midia = 'saudacao_hugo_libras.mp4'; 
        
        if (file_exists($arquivo_sessao)) {
            $sessao = json_decode(file_get_contents($arquivo_sessao), true);
            $etapa_atual = $sessao['etapa'] ?? 0;
            $cliente_id = $sessao['cliente_id'] ?? null;
            $servico_escolhido = $sessao['servico_escolhido'] ?? null;
            $profissional_escolhido = $sessao['profissional_escolhido'] ?? null;
            $data_escolhida = $sessao['data_escolhida'] ?? null;
            $agendamentos_cancelar = $sessao['agendamentos_cancelar'] ?? [];
            $datas_disponiveis = $sessao['datas_disponiveis'] ?? [];
            $horas_disponiveis = $sessao['horas_disponiveis'] ?? [];
        }

        $resposta_texto = "";
        $nova_etapa = $etapa_atual;
        
        $nome_cliente = "";
        try {
            $pdo = new PDO("mysql:host=127.0.0.1;dbname=marketplace;charset=utf8", "root", "");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $oito_ultimos_digitos = substr($numero_limpo, -8);
            $query = $pdo->prepare("SELECT id, nome FROM clientes WHERE telefone LIKE :final LIMIT 1");
            $query->bindValue(':final', '%' . $oito_ultimos_digitos);
            $query->execute();
            
            $cliente = $query->fetch(PDO::FETCH_ASSOC);
            if ($cliente && !empty($cliente['nome'])) {
                $nome_cliente = $cliente['nome'];
                $cliente_id = $cliente['id']; 
            }
        } catch (PDOException $e) {}

        if (!empty($nome_cliente)) {
            $saudacao = "Olá, *" . $nome_cliente . "*! Bem-vindo(a) de volta ao *BarberBot*! 💈";
        } else {
            $saudacao = "Olá! Bem-vindo(a) ao *BarberBot*! 💈";
        }

        // --- INÍCIO DO FILTRO DE PALAVRAS-CHAVE E TRADUÇÃO ---
        
        // 1. LIMPEZA: Remove pontuações que a IA costuma colocar no final de frases curtas (ex: "1.", "Um!")
        $texto_recebido = str_replace(['.', ',', '!', '?'], '', trim($texto_recebido));
        $texto_minusculo = mb_strtolower($texto_recebido, 'UTF-8');

        // 2. TRADUÇÃO: Converte números falados por extenso para algarismos matemáticos
        $numeros_extenso = [
            'zero' => '0', 'um' => '1', 'uma' => '1', 'dois' => '2', 'duas' => '2', 
            'três' => '3', 'tres' => '3', 'quatro' => '4', 'cinco' => '5',
            'seis' => '6', 'sete' => '7', 'oito' => '8', 'nove' => '9', 'dez' => '10'
        ];
        
        // Verifica se a pessoa disse só o número, ou "opção um", "número cinco"
        if (isset($numeros_extenso[$texto_minusculo])) {
            $texto_recebido = $numeros_extenso[$texto_minusculo];
        } else {
            foreach ($numeros_extenso as $palavra => $numero) {
                if ($texto_minusculo === "opção " . $palavra || $texto_minusculo === "opcao " . $palavra || $texto_minusculo === "numero " . $palavra || $texto_minusculo === "número " . $palavra) {
                    $texto_recebido = $numero;
                    break;
                }
            }
        }
        
        // Atualiza a variável minúscula caso a limpeza acima tenha transformado a palavra num número
        $texto_minusculo = mb_strtolower($texto_recebido, 'UTF-8');

        // 3. MAPEAMENTO DE INTENÇÕES (Menu Principal)
        if ($etapa_atual == 0) {
            if (strpos($texto_minusculo, 'agendar') !== false || strpos($texto_minusculo, 'marcar') !== false || strpos($texto_minusculo, 'horário') !== false || strpos($texto_minusculo, 'horario') !== false) {
                $texto_recebido = '1';
            } elseif (strpos($texto_minusculo, 'ver') !== false || strpos($texto_minusculo, 'meus agendamentos') !== false) {
                $texto_recebido = '2';
            } elseif (strpos($texto_minusculo, 'endereço') !== false || strpos($texto_minusculo, 'local') !== false || strpos($texto_minusculo, 'onde') !== false) {
                $texto_recebido = '3';
            } elseif (strpos($texto_minusculo, 'cancelar') !== false || strpos($texto_minusculo, 'desmarcar') !== false) {
                $texto_recebido = '4';
            } elseif (strpos($texto_minusculo, 'encerrar') !== false || strpos($texto_minusculo, 'obrigado') !== false || strpos($texto_minusculo, 'tchau') !== false) {
                $texto_recebido = '5';
            }
        } elseif ($etapa_atual == 1) {
            if (strpos($texto_minusculo, 'cabelo e barba') !== false || strpos($texto_minusculo, 'completo') !== false || strpos($texto_minusculo, 'os dois') !== false) {
                $texto_recebido = '3';
            } elseif (strpos($texto_minusculo, 'corte') !== false || strpos($texto_minusculo, 'cabelo') !== false) {
                $texto_recebido = '1';
            } elseif (strpos($texto_minusculo, 'barba') !== false) {
                $texto_recebido = '2';
            }
        } elseif ($etapa_atual == 2) {
            if (strpos($texto_minusculo, 'joão') !== false || strpos($texto_minusculo, 'joao') !== false) {
                $texto_recebido = '1';
            } elseif (strpos($texto_minusculo, 'marcos') !== false) {
                $texto_recebido = '2';
            }
        }
        // --- FIM DO FILTRO DE PALAVRAS-CHAVE E TRADUÇÃO ---

        if (empty($cliente_id) && $etapa_atual != 100) {
            $resposta_texto = "Olá! Percebi que é o seu primeiro contato com o *BarberBot*! 🎉\n\nPara podermos fazer seus agendamentos, por favor, digite apenas o seu *Nome Completo*:";
            $nova_etapa = 100;
            if (file_exists($caminho_midia)) {
                $enviar_como_media = true;
            }
        } elseif ($etapa_atual == 100) {
            try {
                $stmt = $pdo->prepare("INSERT INTO clientes (nome, telefone) VALUES (:nome, :telefone)");
                $stmt->bindValue(':nome', $texto_recebido);
                $stmt->bindValue(':telefone', $numero_limpo);
                $stmt->execute();
                
                $cliente_id = $pdo->lastInsertId();
                $nome_cliente = $texto_recebido;
                
                $resposta_texto = "Muito prazer, *" . $nome_cliente . "*! Seu cadastro foi feito com sucesso. 💈\n\nDigite a opção desejada:\n\n*1.* Agendar um Horário\n*2.* Ver Meus Agendamentos\n*3.* Endereço\n*4.* Cancelar Agendamento\n*5.* Encerrar Atendimento";
                $nova_etapa = 0;
            } catch (PDOException $e) {
                $resposta_texto = "Ops! Tivemos um pequeno problema ao criar seu cadastro no sistema. Por favor, tente digitar seu nome novamente.";
                $nova_etapa = 100;
            }
        } elseif ($etapa_atual == 0) {
            switch ($texto_recebido) {
                case '1':
                    $resposta_texto = "Ótima escolha! 📅\nQual serviço você deseja agendar?\n\n*1.* Corte de Cabelo\n*2.* Barba\n*3.* Cabelo e Barba\n\n*(Ou digite 0 para voltar)*";
                    $nova_etapa = 1;
                    break;
                case '2':
                    $resposta_texto = "✂ *Seus Próximos Agendamentos:*\n\n";
                    if (!empty($cliente_id)) {
                        try {
                            $stmt = $pdo->prepare("SELECT servico, profissional, data, hora FROM agendamentos WHERE cliente = :cliente_id AND data >= CURDATE() ORDER BY data ASC, hora ASC LIMIT 5");
                            $stmt->bindValue(':cliente_id', $cliente_id);
                            $stmt->execute();
                            $meus_agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (count($meus_agendamentos) > 0) {
                                foreach ($meus_agendamentos as $ag) {
                                    $nome_serv = "Serviço";
                                    if ($ag['servico'] == 1) $nome_serv = "Corte de Cabelo";
                                    if ($ag['servico'] == 2) $nome_serv = "Barba";
                                    if ($ag['servico'] == 3) $nome_serv = "Cabelo e Barba";
                                    
                                    $prof = !empty($ag['profissional']) ? $ag['profissional'] : "Profissional";
                                    $data_br = date('d/m/Y', strtotime($ag['data']));
                                    $hora_formatada = date('H:i', strtotime($ag['hora']));
                                    $resposta_texto .= "🗓️ *" . $data_br . " às " . $hora_formatada . "* - " . $nome_serv . " com *" . $prof . "*\n";
                                }
                            } else {
                                $resposta_texto .= "Nenhum agendamento futuro encontrado. 🤷‍♀️\n";
                            }
                        } catch (PDOException $e) {
                            $resposta_texto .= "Ops! Erro ao consultar o sistema.\n";
                        }
                    } 
                    $resposta_texto .= "\nDigite *0* para voltar ao menu.";
                    $nova_etapa = 99;
                    break;
                case '3':
                    $resposta_texto = "📍 *Nosso Endereço:* Rua da Barbearia, 123.\n\nDigite *0* para voltar.";
                    $nova_etapa = 99;
                    break;
                case '4':
                    if (!empty($cliente_id)) {
                        try {
                            $stmt = $pdo->prepare("SELECT id, servico, profissional, data, hora FROM agendamentos WHERE cliente = :cliente_id AND data >= CURDATE() ORDER BY data ASC LIMIT 5");
                            $stmt->bindValue(':cliente_id', $cliente_id);
                            $stmt->execute();
                            $meus_agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (count($meus_agendamentos) > 0) {
                                $resposta_texto = "🗑️ *Cancelar Agendamento:*\n\nQual destes você deseja cancelar?\n\n";
                                $agendamentos_cancelar = []; 
                                $contador = 1;
                                foreach ($meus_agendamentos as $ag) {
                                    $nome_serv = "Serviço";
                                    if ($ag['servico'] == 1) $nome_serv = "Corte";
                                    if ($ag['servico'] == 2) $nome_serv = "Barba";
                                    if ($ag['servico'] == 3) $nome_serv = "Cabelo e Barba";
                                    $prof = !empty($ag['profissional']) ? $ag['profissional'] : "Profissional";
                                    $data_br = date('d/m/Y', strtotime($ag['data']));
                                    $hora_formatada = date('H:i', strtotime($ag['hora']));
                                    $resposta_texto .= "*" . $contador . ".* " . $data_br . " às " . $hora_formatada . " - " . $nome_serv . " (" . $prof . ")\n";
                                    $agendamentos_cancelar[$contador] = $ag['id'];
                                    $contador++;
                                }
                                $resposta_texto .= "\nDigite o número para cancelar ou *0* para voltar.";
                                $nova_etapa = 5; 
                            } else {
                                $resposta_texto = "Você não tem nenhum agendamento futuro para cancelar. 🤷‍♀️\n\nDigite *0* para voltar.";
                                $nova_etapa = 99;
                            }
                        } catch (PDOException $e) {
                            $resposta_texto = "Erro ao consultar o sistema.\n\nDigite *0* para voltar.";
                            $nova_etapa = 99;
                        }
                    }
                    break;
                case '5':
                    $resposta_texto = "Atendimento encerrado! Foi um prazer falar com você, *" . $nome_cliente . "*. 👋\n\nSempre que precisar, é só mandar um Oi.";
                    $encerrar_sessao = true; 
                    break;
                default:
                    $resposta_texto = $saudacao . "\n\nDigite a opção:\n\n*1.* Agendar um Horário\n*2.* Ver Meus Agendamentos\n*3.* Endereço\n*4.* Cancelar Agendamento\n*5.* Encerrar Atendimento";
                    $nova_etapa = 0;
                    if (file_exists($caminho_midia)) {
                        $enviar_como_media = true;
                    }
                    break;
            }
        } elseif ($etapa_atual == 1) {
            if (in_array($texto_recebido, ['1', '2', '3'])) {
                $servico_escolhido = $texto_recebido;
                $nome_servico = ($texto_recebido == '1') ? "Corte de Cabelo" : (($texto_recebido == '2') ? "Barba" : "Cabelo e Barba");
                
                $resposta_texto = $nome_servico . " selecionado! ✂️\n\nCom qual profissional você prefere realizar o serviço?\n\n*1.* João\n*2.* Marcos\n\n*(Ou digite 0 para voltar)*";
                $nova_etapa = 2; 
            } elseif ($texto_recebido == '0') {
                $resposta_texto = "Voltando...\n\n*1.* Agendar\n*2.* Agendamentos\n*3.* Endereço\n*4.* Cancelar\n*5.* Encerrar"; 
                $nova_etapa = 0;
            } else {
                $resposta_texto = "Opção inválida. Digite 1, 2 ou 3. Ou *0* para voltar.";
            }
        } elseif ($etapa_atual == 2) {
            if (in_array($texto_recebido, ['1', '2'])) {
                $profissional_escolhido = ($texto_recebido == '1') ? "João" : "Marcos";
                
                $dias_semana_pt = ['Sun' => 'Domingo', 'Mon' => 'Segunda-feira', 'Tue' => 'Terça-feira', 'Wed' => 'Quarta-feira', 'Thu' => 'Quinta-feira', 'Fri' => 'Sexta-feira', 'Sat' => 'Sábado'];
                $datas_disponiveis = [];
                $menu_dias = "\n\nPara qual dia da semana?\n\n";
                $contador = 1;
                $dias_adicionados = 0;
                
                $data_base = strtotime('today'); 
                
                while ($dias_adicionados < 6) {
                    $dia_semana = date('D', $data_base);
                    if ($dia_semana != 'Sun') {
                        $data_formatada = date('d/m', $data_base); 
                        $nome_dia = $dias_semana_pt[$dia_semana];
                        
                        if ($data_base == strtotime('today')) {
                            $menu_dias .= "*" . $contador . ".* " . $data_formatada . " (Hoje)\n";
                        } else {
                            $menu_dias .= "*" . $contador . ".* " . $data_formatada . " (" . $nome_dia . ")\n";
                        }
                        
                        $datas_disponiveis[$contador] = date('Y-m-d', $data_base);
                        $contador++;
                        $dias_adicionados++;
                    }
                    $data_base = strtotime('+1 day', $data_base);
                }
                $menu_dias .= "\n*(Ou digite 0 para voltar)*";
                
                $resposta_texto = "Profissional *" . $profissional_escolhido . "* selecionado! 👨‍🎨" . $menu_dias;
                $nova_etapa = 3; 
                
            } elseif ($texto_recebido == '0') {
                $resposta_texto = "Voltando...\n\n*1.* Agendar\n*2.* Agendamentos\n*3.* Endereço\n*4.* Cancelar\n*5.* Encerrar"; 
                $nova_etapa = 0; 
            } else {
                $resposta_texto = "Opção inválida. Digite 1 ou 2, ou *0* para voltar.";
            }
        } elseif ($etapa_atual == 3) {
            if ($texto_recebido == '0') {
                $resposta_texto = "Voltando...\n\n*1.* Agendar\n*2.* Agendamentos\n*3.* Endereço\n*4.* Cancelar\n*5.* Encerrar"; 
                $nova_etapa = 0; 
            } else {
                if (isset($datas_disponiveis[$texto_recebido])) {
                    $data_escolhida = $datas_disponiveis[$texto_recebido];
                    $data_br = date('d/m', strtotime($data_escolhida));
                    
                    try {
                        $stmt = $pdo->prepare("SELECT hora FROM agendamentos WHERE data = :data AND profissional = :profissional");
                        $stmt->bindValue(':data', $data_escolhida);
                        $stmt->bindValue(':profissional', $profissional_escolhido);
                        $stmt->execute();
                        $horarios_ocupados = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        $todos_horarios = ['09:00:00', '10:00:00', '14:00:00', '16:00:00', '18:00:00'];
                        $horas_disponiveis = [];
                        $contador = 1;
                        $menu_horas = "Perfeito! Dia *" . $data_br . "* selecionado.\n\nEscolha o horário para o atendimento com *" . $profissional_escolhido . "*:\n\n";
                        
                        foreach ($todos_horarios as $hora) {
                            $hora_passada = false;
                            if ($data_escolhida == date('Y-m-d') && $hora <= date('H:i:s')) {
                                $hora_passada = true;
                            }

                            if (!in_array($hora, $horarios_ocupados) && !$hora_passada) {
                                $hora_amigavel = date('H:i', strtotime($hora));
                                $menu_horas .= "*" . $contador . ".* " . $hora_amigavel . "\n";
                                $horas_disponiveis[$contador] = $hora;
                                $contador++;
                            }
                        }
                        
                        if ($contador == 1) {
                            $resposta_texto = "Poxa, o profissional *" . $profissional_escolhido . "* está com a agenda lotada ou não possui mais horários disponíveis no dia *" . $data_br . "*. 😔\n\nPor favor, digite *0* para voltar e escolher outro dia ou profissional.";
                            $nova_etapa = 3; 
                        } else {
                            $menu_horas .= "\n*(Ou digite 0 para voltar ao início)*";
                            $resposta_texto = $menu_horas;
                            $nova_etapa = 4; 
                        }
                    } catch (PDOException $e) {
                        $resposta_texto = "Erro ao buscar horários.\n\nDigite *0* para voltar.";
                        $nova_etapa = 99;
                    }
                } else {
                    $resposta_texto = "Opção inválida. Digite o número correspondente ao dia desejado, ou *0* para voltar.";
                    $nova_etapa = 3;
                }
            }
        } elseif ($etapa_atual == 4) {
            if ($texto_recebido == '0') {
                $resposta_texto = "Voltando...\n\n*1.* Agendar\n*2.* Agendamentos\n*3.* Endereço\n*4.* Cancelar\n*5.* Encerrar"; 
                $nova_etapa = 0; 
            } else {
                if (isset($horas_disponiveis[$texto_recebido])) {
                    $hora_escolhida = $horas_disponiveis[$texto_recebido];
                    
                    try {
                        $stmt = $pdo->prepare("INSERT INTO agendamentos (cliente, servico, profissional, data, hora) VALUES (:cliente, :servico, :profissional, :data, :hora)");
                        $stmt->bindValue(':cliente', $cliente_id);
                        $stmt->bindValue(':servico', $servico_escolhido);
                        $stmt->bindValue(':profissional', $profissional_escolhido);
                        $stmt->bindValue(':data', $data_escolhida);
                        $stmt->bindValue(':hora', $hora_escolhida);
                        $stmt->execute();
                        
                        $data_br = date('d/m/Y', strtotime($data_escolhida));
                        $hora_exibir = date('H:i', strtotime($hora_escolhida));
                        
                        $nome_serv_confirmacao = "";
                        if ($servico_escolhido == '1') $nome_serv_confirmacao = "Corte de Cabelo";
                        elseif ($servico_escolhido == '2') $nome_serv_confirmacao = "Barba";
                        elseif ($servico_escolhido == '3') $nome_serv_confirmacao = "Cabelo e Barba";
                        
                        $resposta_texto = "✅ Sensacional! Seu agendamento com o Profissional: *" . $profissional_escolhido . "* 🧔‍♂️ , Serviço: *" . $nome_serv_confirmacao . "* foi salvo para " . $data_br . " às " . $hora_exibir . ".\n\nDigite 0 para voltar ao menu principal.";
                        $nova_etapa = 99;
                        
                        $servico_escolhido = null;
                        $profissional_escolhido = null;
                        $data_escolhida = null;
                        $horas_disponiveis = [];
                    } catch (PDOException $e) {
                        $resposta_texto = "Ops! Tivemos um problema ao salvar. Tente novamente.\n\nDigite *0* para voltar.";
                        $nova_etapa = 99;
                    }
                } else {
                    $resposta_texto = "Horário inválido. Digite uma das opções da lista, ou *0* para voltar.";
                    $nova_etapa = 4; 
                }
            }
        } elseif ($etapa_atual == 5) {
            if ($texto_recebido == '0') {
                $resposta_texto = "Cancelamento abortado.\n\n*1.* Agendar\n*2.* Agendamentos\n*3.* Endereço\n*4.* Cancelar\n*5.* Encerrar";
                $nova_etapa = 0;
                $agendamentos_cancelar = []; 
            } else {
                if (isset($agendamentos_cancelar[$texto_recebido])) {
                    $id_para_apagar = $agendamentos_cancelar[$texto_recebido];
                    try {
                        $stmt = $pdo->prepare("DELETE FROM agendamentos WHERE id = :id AND cliente = :cliente");
                        $stmt->bindValue(':id', $id_para_apagar);
                        $stmt->bindValue(':cliente', $cliente_id);
                        $stmt->execute();
                        
                        $resposta_texto = "✅ Agendamento cancelado com sucesso!\n\nDigite *0* para voltar.";
                        $nova_etapa = 99;
                        $agendamentos_cancelar = []; 
                    } catch (PDOException $e) {
                        $resposta_texto = "Erro ao excluir.\n\nDigite *0* para voltar.";
                        $nova_etapa = 99;
                    }
                } else {
                    $resposta_texto = "Opção inválida. Digite o número exato do agendamento ou *0* para voltar.";
                    $nova_etapa = 5; 
                }
            }
        } elseif ($etapa_atual == 99) {
            if ($texto_recebido == '0') {
                $resposta_texto = $saudacao . "\n\n*1.* Agendar\n*2.* Agendamentos\n*3.* Endereço\n*4.* Cancelar Agendamento\n*5.* Encerrar Atendimento";
                $nova_etapa = 0;
            } else {
                $resposta_texto = "Por favor, digite *0* para voltar ao menu principal.";
            }
        }

        if ($encerrar_sessao) {
            if (file_exists($arquivo_sessao)) {
                unlink($arquivo_sessao); 
            }
        } else {
            $dados_sessao = [
                'ultimo_contato' => time(),
                'etapa' => $nova_etapa,
                'cliente_id' => $cliente_id,
                'servico_escolhido' => $servico_escolhido,
                'profissional_escolhido' => $profissional_escolhido,
                'data_escolhida' => $data_escolhida, 
                'agendamentos_cancelar' => $agendamentos_cancelar,
                'datas_disponiveis' => $datas_disponiveis,
                'horas_disponiveis' => $horas_disponiveis
            ];
            file_put_contents($arquivo_sessao, json_encode($dados_sessao));
        }

        if ($enviar_como_media && file_exists($caminho_midia)) {
            $video_base64 = base64_encode(file_get_contents($caminho_midia));
            $payload = [
                "number" => $numero_limpo,
                "mediatype" => "video",
                "media" => $video_base64,
                "caption" => $resposta_texto
            ];
            $url_envio = "http://127.0.0.1:8080/message/sendMedia/MeuBotPHP";
        } else {
            $payload = [
                "number" => $numero_limpo,
                "text" => $resposta_texto
            ];
            $url_envio = "http://127.0.0.1:8080/message/sendText/MeuBotPHP";
        }

        $ch = curl_init($url_envio);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: BarberBot123", "Content-Type: application/json"]);
        curl_exec($ch);
        curl_close($ch);
    }
}
http_response_code(200);
?>