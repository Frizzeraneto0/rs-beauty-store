<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'checkout.php';
    header('Location: login.php');
    exit;
}

$db = getPDO();

$usuario = $db->prepare("SELECT * FROM usuarios WHERE id = :id");
$usuario->execute([':id' => $_SESSION['user_id']]);
$usuario = $usuario->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$tiposEndereco = $db->query("SELECT * FROM tipo_endereco ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

define('STORE_URL', 'https://rs-beauty-store.com');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra - RS BEAUTY STORE</title>
    <?php include __DIR__ . '/partials/navbar.php'; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --black: #0a0a0a;
            --white: #fefefe;
            --gray-light: #f5f5f5;
            --gray-mid: #e0e0e0;
            --gray-dark: #666;
            --accent: #d4af37;
            --rose-gold: #E8B4B8;
            --deep-rose: #C67B88;
            --soft-pink: #FFF5F7;
            --luxury-purple: #9B7EBD;
            --green-pix: #32bcad;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, var(--white) 0%, var(--soft-pink) 50%, var(--white) 100%);
            color: var(--black);
            line-height: 1.6;
            min-height: 100vh;
        }

        .checkout-container {
            max-width: 1400px;
            margin: 2rem auto 3rem;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 3rem;
        }

        .checkout-main { display: flex; flex-direction: column; gap: 2rem; }

        .section-card {
            background: var(--white); border-radius: 20px;
            padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        }

        .section-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.8rem; font-weight: 600; margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--black), var(--deep-rose));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* FORM */
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; font-weight: 600; margin-bottom: 0.5rem; font-size: 0.9rem; }
        .form-input {
            width: 100%; padding: 1rem;
            border: 2px solid var(--gray-mid); border-radius: 12px;
            font-size: 0.95rem; font-family: inherit; transition: all 0.3s;
        }
        .form-input:focus { outline: none; border-color: var(--rose-gold); box-shadow: 0 4px 15px rgba(232,180,184,0.2); }
        .form-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }

        /* TIPO ENDEREÇO */
        .tipo-endereco-options { display: flex; gap: 1rem; flex-wrap: wrap; }
        .tipo-option {
            display: flex; align-items: center; gap: 0.6rem;
            padding: 0.8rem 1.4rem; border: 2px solid var(--gray-mid);
            border-radius: 10px; cursor: pointer; transition: all 0.3s;
            font-size: 0.9rem; font-weight: 500;
        }
        .tipo-option:hover { border-color: var(--rose-gold); background: var(--soft-pink); }
        .tipo-option.active {
            border-color: var(--deep-rose);
            background: linear-gradient(135deg, var(--soft-pink), rgba(232,180,184,0.3));
            font-weight: 700;
        }

        /* ===== PAYMENT ===== */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .payment-option {
            padding: 1.4rem 1rem;
            border: 2px solid var(--gray-mid);
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            position: relative;
        }

        .payment-option:hover {
            border-color: var(--rose-gold);
            background: var(--soft-pink);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(232,180,184,0.25);
        }

        .payment-option.active {
            border-color: var(--deep-rose);
            background: linear-gradient(135deg, var(--soft-pink), rgba(232,180,184,0.3));
            box-shadow: 0 6px 20px rgba(198,123,136,0.2);
        }

        .payment-option.active::after {
            content: '✓';
            position: absolute; top: 0.5rem; right: 0.7rem;
            font-size: 0.75rem; font-weight: 700;
            color: var(--deep-rose);
        }

        /* Desabilitado (ex: boleto não suportado pela AbacatePay) */
        .payment-option.disabled {
            opacity: 0.38;
            cursor: not-allowed;
            pointer-events: none;
        }

        .payment-icon { font-size: 2rem; margin-bottom: 0.4rem; }
        .payment-name { font-weight: 700; font-size: 0.9rem; margin-bottom: 0.2rem; }
        .payment-desc { font-size: 0.75rem; color: var(--gray-dark); }

        /* Badge PIX */
        .pix-info {
            display: none;
            margin-top: 1.2rem;
            padding: 1rem 1.4rem;
            background: linear-gradient(135deg, #f0faf9, #e6f7f5);
            border: 1.5px solid var(--green-pix);
            border-radius: 12px;
            font-size: 0.82rem;
            color: #1a6b5a;
            font-weight: 500;
            line-height: 1.6;
        }
        .pix-info.visible { display: block; }

        /* Badge cartão */
        .card-info {
            display: none;
            margin-top: 1.2rem;
            padding: 1rem 1.4rem;
            background: linear-gradient(135deg, #f5f0ff, #ede6ff);
            border: 1.5px solid var(--luxury-purple);
            border-radius: 12px;
            font-size: 0.82rem;
            color: #4a3070;
            font-weight: 500;
            line-height: 1.6;
        }
        .card-info.visible { display: block; }

        /* Badge AbacatePay */
        .abacatepay-badge {
            margin-top: 1.4rem;
            padding: 0.8rem 1.2rem;
            background: var(--gray-light);
            border-radius: 10px;
            display: flex; align-items: center; gap: 0.8rem;
            font-size: 0.78rem; color: var(--gray-dark);
        }

        /* ERRO */
        .error-msg {
            background: #fff0f0; border: 1.5px solid #f5a0a0;
            border-radius: 10px; padding: 0.8rem 1.2rem;
            color: #c0392b; font-size: 0.85rem; margin-top: 1rem;
            display: none;
        }
        .error-msg.visible { display: block; }

        /* ORDER SUMMARY */
        .order-summary { position: sticky; top: 120px; height: fit-content; }
        .summary-card {
            background: linear-gradient(135deg, var(--white), var(--soft-pink));
            border-radius: 20px; padding: 2rem;
            box-shadow: 0 10px 40px rgba(198,123,136,0.15);
        }
        .summary-items { max-height: 300px; overflow-y: auto; margin-bottom: 1.5rem; }
        .summary-item { display: flex; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid var(--gray-mid); }
        .summary-item:last-child { border-bottom: none; }
        .summary-item-image { width: 60px; height: 60px; background: var(--gray-light); border-radius: 8px; overflow: hidden; flex-shrink: 0; }
        .summary-item-image img { width: 100%; height: 100%; object-fit: cover; }
        .summary-item-details { flex: 1; font-size: 0.85rem; }
        .summary-item-name { font-weight: 600; margin-bottom: 0.3rem; }
        .summary-item-variations { color: var(--gray-dark); font-size: 0.75rem; margin-bottom: 0.3rem; }
        .summary-item-price { font-weight: 600; }
        .summary-row { display: flex; justify-content: space-between; padding: 0.8rem 0; border-bottom: 1px solid var(--gray-mid); }
        .summary-row:last-child { border-bottom: none; }
        .summary-total { margin-top: 1rem; padding-top: 1.5rem; border-top: 2px solid var(--deep-rose); font-size: 1.2rem; font-weight: 700; }
        .summary-total .summary-value {
            background: linear-gradient(135deg, var(--deep-rose), var(--luxury-purple));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text; font-size: 1.8rem;
        }

        .btn-place-order {
            width: 100%; padding: 1.2rem; margin-top: 1.5rem;
            background: linear-gradient(135deg, var(--black) 0%, var(--deep-rose) 100%);
            color: var(--white); border: none; border-radius: 12px;
            font-size: 1rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;
            cursor: pointer; transition: all 0.4s;
            box-shadow: 0 8px 25px rgba(198,123,136,0.3);
        }
        .btn-place-order:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--deep-rose) 0%, var(--luxury-purple) 100%);
            transform: translateY(-3px); box-shadow: 0 12px 35px rgba(155,126,189,0.4);
        }
        .btn-place-order:disabled { opacity: 0.6; cursor: not-allowed; }

        .loading { display: none; text-align: center; padding: 2rem; }
        .loading.active { display: block; }
        .spinner {
            border: 4px solid var(--gray-mid); border-top: 4px solid var(--deep-rose);
            border-radius: 50%; width: 50px; height: 50px;
            animation: spin 1s linear infinite; margin: 0 auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 968px) {
            .checkout-container { grid-template-columns: 1fr; }
            .order-summary { position: static; }
            .form-row { grid-template-columns: 1fr; }
            .payment-methods { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="checkout-container">
    <div class="checkout-main">

        <!-- INFORMAÇÕES DE ENTREGA -->
        <div class="section-card">
            <h2 class="section-title">Informações de Entrega</h2>
            <form id="checkoutForm">
                <div class="form-group">
                    <label class="form-label">Nome Completo</label>
                    <input type="text" class="form-input" id="nome"
                           value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" id="email"
                               value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telefone (WhatsApp)</label>
                        <input type="tel" class="form-input" id="telefone"
                               value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>"
                               placeholder="(00) 00000-0000"
                               oninput="mascaraTelefone(this)" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">CPF / CNPJ <span style="color:var(--deep-rose)">*</span></label>
                    <input type="text" class="form-input" id="taxId"
                           value="<?= htmlspecialchars($usuario['cpf'] ?? '') ?>"
                           placeholder="000.000.000-00"
                           oninput="mascaraCpfCnpj(this)" required>
                    <small style="color:var(--gray-dark);font-size:0.78rem;margin-top:0.3rem;display:block;">
                        Necessário para emissão da cobrança
                    </small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">CEP</label>
                        <input type="text" class="form-input" id="cep"
                               placeholder="00000-000" maxlength="9"
                               oninput="mascaraCEP(this)" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cidade</label>
                        <input type="text" class="form-input" id="cidade" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Endereço</label>
                    <input type="text" class="form-input" id="endereco"
                           placeholder="Rua, número" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Bairro</label>
                        <input type="text" class="form-input" id="bairro" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Complemento</label>
                        <input type="text" class="form-input" id="complemento">
                    </div>
                </div>

                <input type="hidden" id="estado" value="">

                <div class="form-group">
                    <label class="form-label">Tipo de Endereço</label>
                    <div class="tipo-endereco-options">
                        <?php foreach ($tiposEndereco as $tipo): ?>
                            <div class="tipo-option <?= $tipo === reset($tiposEndereco) ? 'active' : '' ?>"
                                 data-id="<?= $tipo['id'] ?>"
                                 onclick="selecionarTipoEndereco(<?= $tipo['id'] ?>, this)">
                                <?php
                                $icones = ['Residencial' => '🏠', 'Comercial' => '🏢'];
                                echo $icones[$tipo['descricao']] ?? '📍';
                                ?>
                                <?= htmlspecialchars($tipo['descricao']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="tipo_endereco_id"
                           value="<?= htmlspecialchars($tiposEndereco[0]['id'] ?? 1) ?>">
                </div>
            </form>
        </div>

        <!-- FORMA DE PAGAMENTO -->
        <div class="section-card">
            <h2 class="section-title">Forma de Pagamento</h2>
            <div class="payment-methods" id="paymentMethods">

                <div class="payment-option" data-metodo="pix"
                     onclick="selecionarPagamento('pix', this)">
                    <div class="payment-icon">📱</div>
                    <div class="payment-name">PIX</div>
                    <div class="payment-desc">Aprovação imediata</div>
                </div>

                <div class="payment-option" data-metodo="cartao_credito"
                     onclick="selecionarPagamento('cartao_credito', this)">
                    <div class="payment-icon">💳</div>
                    <div class="payment-name">Cartão de Crédito</div>
                    <div class="payment-desc">Até 12x sem juros</div>
                </div>

                <!-- Boleto: não suportado pela AbacatePay, desabilitado -->
                <div class="payment-option disabled" data-metodo="boleto" title="Indisponível no momento">
                    <div class="payment-icon">🏦</div>
                    <div class="payment-name">Boleto</div>
                    <div class="payment-desc">Indisponível</div>
                </div>

                <div class="payment-option" data-metodo="cartao_debito"
                     onclick="selecionarPagamento('cartao_debito', this)">
                    <div class="payment-icon">💵</div>
                    <div class="payment-name">Cartão de Débito</div>
                    <div class="payment-desc">Desconto à vista</div>
                </div>

            </div>

            <!-- Info contextual por método -->
            <div class="pix-info" id="pixInfo">
                ✓ Após confirmar o pedido, você será redirecionado para a página de pagamento onde
                poderá escanear o QR Code PIX. Aprovação automática e imediata.
            </div>
            <div class="card-info" id="cardInfo">
                🔒 Seus dados de cartão são inseridos diretamente no ambiente seguro da AbacatePay,
                com criptografia de ponta a ponta. Nenhum dado é armazenado em nossos servidores.
            </div>

            <div class="abacatepay-badge">
                <span style="font-size:1.4rem">🥑</span>
                <span>Pagamento processado com segurança via <strong>AbacatePay</strong></span>
            </div>

            <div class="error-msg" id="erroCheckout"></div>
        </div>

    </div>

    <!-- RESUMO DO PEDIDO -->
    <div class="order-summary">
        <div class="summary-card">
            <h3 class="section-title">Resumo do Pedido</h3>
            <div class="summary-items" id="summaryItems"></div>

            <div class="summary-row">
                <span>Subtotal</span>
                <span class="summary-value" id="subtotal">R$ 0,00</span>
            </div>
            <div class="summary-row">
                <span>Frete</span>
                <span class="summary-value">Grátis</span>
            </div>
            <div class="summary-row summary-total">
                <span>Total</span>
                <span class="summary-value" id="total">R$ 0,00</span>
            </div>

            <button class="btn-place-order" id="btnFinalizar" onclick="finalizarCompra()">
                🔒 Ir para Pagamento
            </button>
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p style="margin-top:1rem;color:var(--gray-dark)">Criando cobrança segura…</p>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================================
// Mapeamento: método local → method(s) AbacatePay
// AbacatePay aceita: "PIX" e "CARD"
// ============================================================
const METODO_MAP = {
    pix:            { methods: ['PIX'],         label: 'PIX' },
    cartao_credito: { methods: ['CARD'],        label: 'Cartão de Crédito' },
    cartao_debito:  { methods: ['CARD'],        label: 'Cartão de Débito' },
};

let carrinho             = [];
let pagamentoSelecionado = null; // objeto { metodo, methods, label }

// ============================================================
// CARRINHO
// ============================================================
function carregarCarrinho() {
    carrinho = JSON.parse(localStorage.getItem('carrinho') || '[]');
    if (carrinho.length === 0) { window.location.href = 'carrinho.php'; return; }
    renderizarResumo();
}

function renderizarResumo() {
    document.getElementById('summaryItems').innerHTML = carrinho.map(item => `
        <div class="summary-item">
            <div class="summary-item-image">
                ${item.imagem
                    ? `<img src="${item.imagem}" alt="${item.nome}">`
                    : '<div style="width:100%;height:100%;background:var(--gray-mid)"></div>'}
            </div>
            <div class="summary-item-details">
                <div class="summary-item-name">${item.nome}</div>
                ${item.variacoesTexto ? `<div class="summary-item-variations">${item.variacoesTexto}</div>` : ''}
                <div class="summary-item-price">
                    ${item.quantidade}x R$ ${parseFloat(item.preco).toFixed(2).replace('.',',')} =
                    R$ ${(parseFloat(item.preco)*item.quantidade).toFixed(2).replace('.',',')}
                </div>
            </div>
        </div>
    `).join('');
    atualizarTotais();
}

function atualizarTotais() {
    const sub = carrinho.reduce((t,i) => t + parseFloat(i.preco)*i.quantidade, 0);
    document.getElementById('subtotal').textContent = `R$ ${sub.toFixed(2).replace('.',',')}`;
    document.getElementById('total').textContent    = `R$ ${sub.toFixed(2).replace('.',',')}`;
}

// ============================================================
// SELEÇÃO DE PAGAMENTO
// ============================================================
function selecionarPagamento(metodo, el) {
    // Remove active de todos
    document.querySelectorAll('.payment-option:not(.disabled)').forEach(e => e.classList.remove('active'));
    el.classList.add('active');

    pagamentoSelecionado = { metodo, ...METODO_MAP[metodo] };

    // Exibir info contextual
    document.getElementById('pixInfo').classList.toggle('visible',  metodo === 'pix');
    document.getElementById('cardInfo').classList.toggle('visible', metodo === 'cartao_credito' || metodo === 'cartao_debito');
}

function selecionarTipoEndereco(id, el) {
    document.querySelectorAll('.tipo-option').forEach(e => e.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('tipo_endereco_id').value = id;
}

// ============================================================
// MÁSCARAS
// ============================================================
function mascaraCEP(input) {
    let v = input.value.replace(/\D/g,'');
    if (v.length > 5) v = v.slice(0,5) + '-' + v.slice(5,8);
    input.value = v;
    if (v.replace('-','').length === 8) buscarCEP(v.replace('-',''));
}

function mascaraTelefone(input) {
    let v = input.value.replace(/\D/g,'').slice(0,11);
    if (v.length > 6)      v = `(${v.slice(0,2)}) ${v.slice(2,7)}-${v.slice(7)}`;
    else if (v.length > 2) v = `(${v.slice(0,2)}) ${v.slice(2)}`;
    input.value = v;
}

function mascaraCpfCnpj(input) {
    let v = input.value.replace(/\D/g,'');
    if (v.length <= 11) {
        v = v.replace(/(\d{3})(\d)/,'$1.$2')
             .replace(/(\d{3})(\d)/,'$1.$2')
             .replace(/(\d{3})(\d{1,2})$/,'$1-$2');
    } else {
        v = v.slice(0,14)
             .replace(/^(\d{2})(\d)/,'$1.$2')
             .replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3')
             .replace(/\.(\d{3})(\d)/,'.$1/$2')
             .replace(/(\d{4})(\d)/,'$1-$2');
    }
    input.value = v;
}

async function buscarCEP(cep) {
    try {
        const data = await fetch(`https://viacep.com.br/ws/${cep}/json/`).then(r => r.json());
        if (!data.erro) {
            document.getElementById('endereco').value = data.logradouro || '';
            document.getElementById('bairro').value   = data.bairro     || '';
            document.getElementById('cidade').value   = data.localidade || '';
            document.getElementById('estado').value   = data.uf         || '';
        }
    } catch(e) {}
}

// ============================================================
// ERROS
// ============================================================
function mostrarErro(msg) {
    const el = document.getElementById('erroCheckout');
    el.textContent = msg;
    el.classList.add('visible');
    el.scrollIntoView({ behavior:'smooth', block:'center' });
}
function esconderErro() {
    document.getElementById('erroCheckout').classList.remove('visible');
}

// ============================================================
// FINALIZAR COMPRA
// ============================================================
async function finalizarCompra() {
    esconderErro();

    if (!document.getElementById('checkoutForm').checkValidity()) {
        document.getElementById('checkoutForm').reportValidity();
        return;
    }

    if (!pagamentoSelecionado) {
        mostrarErro('Por favor, selecione uma forma de pagamento.');
        return;
    }

    const taxId = document.getElementById('taxId').value.replace(/\D/g,'');
    if (taxId.length !== 11 && taxId.length !== 14) {
        mostrarErro('Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.');
        return;
    }

    const subtotal = carrinho.reduce((t,i) => t + parseFloat(i.preco)*i.quantidade, 0);

    const dados = {
        carrinho,
        metodo_pagamento:    pagamentoSelecionado.metodo,   // ex: "pix"
        abacate_methods:     pagamentoSelecionado.methods,  // ex: ["PIX"]
        valor_total:         subtotal,
        taxId,
        endereco: {
            tipo_endereco_id: document.getElementById('tipo_endereco_id').value,
            nome:             document.getElementById('nome').value,
            email:            document.getElementById('email').value,
            telefone:         document.getElementById('telefone').value.replace(/\D/g,''),
            cep:              document.getElementById('cep').value,
            cidade:           document.getElementById('cidade').value,
            estado:           document.getElementById('estado').value || 'ES',
            endereco:         document.getElementById('endereco').value,
            bairro:           document.getElementById('bairro').value,
            complemento:      document.getElementById('complemento').value,
        }
    };

    document.getElementById('loading').classList.add('active');
    document.getElementById('btnFinalizar').style.display = 'none';

    try {
        const response = await fetch('../api.php?action=finalizar_compra', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(dados)
        });

        const text = await response.text();
        let result;
        try { result = JSON.parse(text); }
        catch(e) { throw new Error('Resposta inválida do servidor'); }

        if (result.success && result.payment_url) {
            localStorage.removeItem('carrinho');
            window.location.href = result.payment_url;
        } else {
            throw new Error(result.message || 'Erro desconhecido');
        }
    } catch(error) {
        mostrarErro('Erro ao processar pedido: ' + error.message + '. Tente novamente.');
        document.getElementById('loading').classList.remove('active');
        document.getElementById('btnFinalizar').style.display = 'block';
    }
}

carregarCarrinho();
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>