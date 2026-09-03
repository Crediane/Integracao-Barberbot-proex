<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Aprovado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"> <!-- Atualização do CDN -->
    <style>
        body {
            background-color: #f8f9fa;
        }
        .payment-success {
            margin-top: 50px;
            text-align: center;
        }
        .payment-success-icon {
            font-size: 80px;
            color: #28a745;
        }
        .payment-success h1 {
            margin-top: 20px;
            font-size: 36px;
        }
        .payment-success p {
            font-size: 18px;
            color: #6c757d;
        }
        .btn-home {
            margin-top: 30px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="payment-success">
                <div class="payment-success-icon">
                    <i class="bi bi-check-circle-fill"></i> <!-- Ícone de aprovado -->
                </div>
                <h1>Pagamento Aprovado!</h1>
                <p>Obrigado pela sua compra. Seu pagamento foi processado com sucesso.</p>
                
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


 <script>
        // Redireciona após 3 segundos
        setTimeout(function() {
            window.location='../meus-agendamentos.php';
            
        }, 3000);
    </script>