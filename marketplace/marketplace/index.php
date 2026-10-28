<?php
// /public_html/marketplace/index.php
// A pasta física /marketplace/ contém apenas assets e componentes.
// A listagem oficial usa /public_html/marketplace.php, que trabalha
// exclusivamente com os bancos globais do Marketplace/Admin.
header('Location: /marketplace.php/', true, 301);
exit;
