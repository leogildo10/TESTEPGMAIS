<?php
$retorno = '';
$numVerifyKEY = '51366086e5b909d0f2309b843e8917b0'; // criar sua chave de API no link, em breve irei desativar esta chave: https://numverify.com/

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // file sent from form 

    if (isset($_FILES['arquivo'])) { // existe arquivo?
        $errors = array();
        $file_name = $_FILES['arquivo']['name']; // obter nome do arquivo
        $file_size = $_FILES['arquivo']['size']; // obter o tamanho do arquivo
        $file_tmp = $_FILES['arquivo']['tmp_name']; // obter o local temporário aramzenado no servidor
        $file_type = $_FILES['arquivo']['type']; // obter o tipo do arquivo, txt json e etc
        $file_ext = substr(strrchr($file_name, '.'), 1); // obter a extensão do arquivo. strrchr extrai a string após o última (.) e substr corta o (.)   

        $expensions = array("txt");

        if (in_array($file_ext, $expensions) === false) { // arquivo não é da extensão txt?
            $errors[] = "Extensão Não Permitida, Favor Escolher Um Arquivo no Formato: txt";
        }

        if ($file_size > 2097152) { // o tamanho do arquivo é maior que 2 MEGAS?
            $errors[] = 'O Arquivo Deve Ter o Tamanho Máximo de 2 MegaByte';
        }

        // se não existe nenhum erro com o arquivo, prossegue na avaliação
        if (empty($errors) == true) {

            $filestring = file_get_contents($file_tmp); // obter conteúdo do arquivo
            $filearray = explode("\n", $filestring); // quebrar o conteu do arquivo wem parágrafo
            $telefoneValido = true;
            $numeroBlackList = false;
            $dddSaoPaulo = false;
            $dataLimiteAcima = false;
            $mensagemAcimaLimite = false;
            $arrayTemp = array(); // 

            foreach ($filearray as $key => $valueAtual) {
                if (!empty($valueAtual)) {
                    // echo gettype($value); // STRING // echo $valueAtual; // print_r($arrayvalues[0]); // echo "<br />";
                    $arrayvalues = explode(";", $valueAtual);
                    $idMensagem = $arrayvalues[0]; // obter id da mensagem
                    $ddd = $arrayvalues[1]; // obter o ddd
                    $celular = $arrayvalues[2]; // obter o celular
                    $operadora = $arrayvalues[3]; // obter a operadora
                    $horarioEnvio = $arrayvalues[4]; // obter o horário d envio
                    $mensagem = $arrayvalues[5]; // obter a mensagem

                    // verificar se o telefone é VÁLIDO baseado nas seguintes regras:
                    // DDD com 2 digitos; // strlen($ddd);
                    // DDD deve ser válido (diferente de 00); // strcmp($ddd, 00); // If this function returns 0, the two strings are equal.
                    // número celular deve conter 9 dígitos; // strlen($celular);
                    // numero celular deve começar com 9; // $celular[0];
                    // o segundo dígito deve ser > 6; // $celular[1];

                    if (strlen($ddd) == 2 && strcmp($ddd, 00) !== 0 && strlen($celular) == 9 && $celular[0] == 9 && $celular[1] > 6) {
                        // verificar se o telefone é VÁLIDO baseado na API
                        // uma verificação extra
                        $urlCheckPhone = "http://apilayer.net/api/validate?access_key=" . $numVerifyKEY . "&number=" . $ddd . $celular . "&country_code=BR&format=1";
                        $clientCheckPhone = curl_init($urlCheckPhone);
                        curl_setopt($clientCheckPhone, CURLOPT_RETURNTRANSFER, true);
                        $response = curl_exec($clientCheckPhone);
                        $resultCheckPhone = json_decode($response, true);
                        $numValid = array_values($resultCheckPhone);
                        if ($numValid[0] == 1) {
                            // echo "Número Válido.";
                            // echo "<br />";
                            $telefoneValido = true;
                        } else {
                            // echo "Número Inválido.";
                            // echo "<br />";
                            $telefoneValido = false;
                        }
                        // Close curls
                        curl_close($clientCheckPhone);
                    } else {
                        // echo "Número Inválido.";
                        // echo "<br />";
                        $telefoneValido = false;
                    }


                    // verificar se o telefone esta na Black List
                    // https://front-test-pg.herokuapp.com/blacklist/
                    $urlBlackList = "https://front-test-pg.herokuapp.com/blacklist/" . $ddd . $celular;
                    $clientBlackList = curl_init($urlBlackList);
                    curl_setopt($clientBlackList, CURLOPT_RETURNTRANSFER, true);
                    $response = curl_exec($clientBlackList);
                    $resultBlackList = json_decode($response);
                    if (isset($resultBlackList->phone)) {
                        // echo "numero na black list";
                        // echo "<br />";
                        $numeroBlackList = true;
                    } else {
                        // echo "numero livre da black list";
                        // echo "<br />";
                        $numeroBlackList = false;
                    }
                    // Close curls
                    curl_close($clientBlackList);

                    // verificar se o DDD é do estado de são paulo
                    // segundo o site, os DDDS da cidade de São Paulo, vai de 11 ate o 19
                    // http://www.ddi-ddd.com.br/Codigos-Telefone-Brasil/Regiao-Sao-Paulo/
                    // https://gist.github.com/ThadeuLuz/797b60972f74f3080b32642eb36481a5
                    if ($ddd > 10 && $ddd < 20) { // se o dd estiver entre 11 a 19
                        // echo "DDD Pertence a São Paulo";
                        // echo "<br />";
                        $dddSaoPaulo = true;
                    } else {
                        // echo "DDD Não é São Paulo";
                        // echo "<br />";
                        $dddSaoPaulo = false;
                    }

                    // verificar se o agendamento da mensagem esta para após as 19:59:59
                    if ($horarioEnvio > '19:59:59') {
                        // echo "data de Envio Acima do Limite ";
                        // echo "<br />";
                        $dataLimiteAcima = true;
                    } else {
                        // echo "data de Envio Abaixo do Limite ";
                        // echo "<br />";
                        $dataLimiteAcima = false;
                    }

                    // verificar se a mensagem esta possui mais de 140 caracteres
                    if (strlen($mensagem) > 140) {
                        // echo "Mensagem Acima do Limite de Caracteres";
                        // echo "<br />";
                        $mensagemAcimaLimite = true;
                    } else {
                        // echo "Mensagem Abaixo do Limite de Caracteres";
                        // echo "<br />";
                        $mensagemAcimaLimite = false;
                    }

                    // proseeguir com a análise caso a mesnagem se encaixe nos critérios
                    // o número é válido
                    // o número não esta na black list
                    // o ddd não é do Estado de São Paulo
                    // a data de envio não é acima das 19:59:59
                    // conteúdo da mensagem não tem mais de 140 caracteres
                    if ($telefoneValido == true && $numeroBlackList == false && $dddSaoPaulo == false && $dataLimiteAcima == false && $mensagemAcimaLimite == false) {
                        // array_push($arrayTemp, $valueAtual);
                        // e por último. verificar a duplicadade no telefone de destino
                        // se o número do destino tiver mais de 1, enviar a mensagem com a menor data de envio
                        $index = 0;
                        if (count($arrayTemp) > 0) {
                            foreach ($arrayTemp as $index => $valueTemp) {
                                $valueTemp = explode(";", $valueTemp);
                                $phoneTemp = $valueTemp[1] . $valueTemp[2];
                                $phoneAtual = $ddd . $celular;

                                if ($phoneAtual == $phoneTemp) { // phone destinatário atual igual ao phone destinatário do array?
                                    if ($horarioEnvio < $valueTemp[4]) { // horario de envio atual menor que o horário de envio na variavel TEMP?
                                        // remover este index do array TEMP
                                        //echo print_r($arrayTemp);
                                        // echo "<br />";
                                        unset($arrayTemp[$index]);
                                        //echo print_r($arrayTemp);
                                        //echo "<br />";
                                        // adicionar o valor com a data menor no array
                                        array_push($arrayTemp, $valueAtual);
                                        $arrayTemp = array_unique($arrayTemp); // remove valor duplicado
                                        $index++;
                                    }
                                } else {
                                    array_push($arrayTemp, $valueAtual);
                                    $arrayTemp = array_unique($arrayTemp); // remove valor duplicado
                                    $index++;
                                }
                            }
                        } else {
                            array_push($arrayTemp, $valueAtual);
                            $arrayTemp = array_unique($arrayTemp); // remove valor duplicado
                        }
                    }
                }
            }
            // fim do foreach
            // pegar os dados do array TEMP, formatar e armazenar na variável de retorno
            foreach ($arrayTemp as $valueTemp2) {
                $valueTemp2 = explode(";", $valueTemp2);
                //  print_r($valueTemp2);
                //   echo "<br />";
                // definir o IDBROCKER
                if ($valueTemp2[3] == 'VIVO' || $valueTemp2[3] == 'TIM')
                    $broker = 1;
                else if ($valueTemp2[3] == 'CLARO' || $valueTemp2[3] == 'OI')
                    $broker = 2;
                else
                    $broker = 3;
                $resultado = $valueTemp2[0] . ';' . $broker . '<br />';
                $retorno = $retorno . $resultado;
            }
            echo $retorno;
        } else {
            print_r($errors);
        }
    }
}
?>

<!doctype html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Index</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
    <link href="assets/vendor/fonts/circular-std/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/libs/css/style.css">
    <link rel="stylesheet" href="assets/vendor/fonts/fontawesome/css/fontawesome-all.css">
    <style>
        html,
        body {
            height: 100%;
        }

        body {
            display: -ms-flexbox;
            display: flex;
            -ms-flex-align: center;
            align-items: center;
            padding-top: 40px;
            padding-bottom: 40px;
        }
    </style>
</head>

<body>
    <!-- ============================================================== -->
    <!-- index page  -->
    <!-- ============================================================== -->
    <div class="splash-container">
        <div class="card ">
            <div class="card-header text-center">
                <!-- variavle retorno, aonde sera mostrado o resultado final -->
                <?php echo $retorno  ?>
                <hr>
                <span class="splash-description">Selecione Algum Arquivo do Tipo txt</span>
            </div>
            <div class="card-body">
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <input type="file" id="arquivo" name="arquivo">
                    </div>
                    <div class="form-group">

                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-block">Verificar</button>
                </form>
            </div>
        </div>
    </div>
    <!-- ============================================================== -->
    <!-- end index page  -->
    <!-- ============================================================== -->
    <!-- Optional JavaScript -->
    <script src="assets/vendor/jquery/jquery-3.3.1.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.js"></script>
</body>

</html>
