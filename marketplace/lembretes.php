<?php
// lembretes.php - Script proativo para enviar lembretes de agendamentos do dia

try {
    // Conexão com o banco de dados
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=marketplace;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Busca os agendamentos de HOJE (CURDATE) unindo com a tabela de clientes para pegar o telefone
    $stmt = $pdo->prepare("
        SELECT a.servico, a.profissional, a.hora, c.nome, c.telefone 
        FROM agendamentos a 
        JOIN clientes c ON a.cliente = c.id 
        WHERE a.data = CURDATE()
    ");
    $stmt->execute();
    $agendamentos_hoje = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $enviados = 0;

    if (count($agendamentos_hoje) > 0) {
        foreach ($agendamentos_hoje as $ag) {
            $nome_cliente = $ag['nome'];
            $numero_cliente = $ag['telefone'];
            $profissional = $ag['profissional'];
            $hora_formatada = date('H:i', strtotime($ag['hora']));

            $nome_servico = "Serviço";
            if ($ag['servico'] == 1) $nome_servico = "Corte de Cabelo";
            if ($ag['servico'] == 2) $nome_servico = "Barba";
            if ($ag['servico'] == 3) $nome_servico = "Cabelo e Barba";

            // Monta a mensagem utilizando o padrão que aprovamos anteriormente
            $mensagem = "Olá, *" . $nome_cliente . "*! ☀️\n\nPassando para lembrar do seu horário hoje às *" . $hora_formatada . "* com o Profissional: *" . $profissional . "* 🧔‍♂️, Serviço: *" . $nome_servico . "*.\n\nTe esperamos lá!";

            // Envia via Evolution API usando as mesmas credenciais do seu webhook
            $ch = curl_init("http://127.0.0.1:8080/message/sendText/MeuBotPHP");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                "number" => $numero_cliente, 
                "text" => $mensagem
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "apikey: BarberBot123", 
                "Content-Type: application/json"
            ]);
            curl_exec($ch);
            curl_close($ch);

            $enviados++;
            
            // Pausa de 2 segundos para não sobrecarregar a Evolution API se houver muitos clientes no dia
            sleep(2);
        }
        echo "Lembretes proativos enviados com sucesso: " . $enviados;
    } else {
        echo "Nenhum agendamento encontrado para a data de hoje.";
    }

} catch (PDOException $e) {
    echo "Erro na comunicação com o banco de dados: " . $e->getMessage();
}
?>