<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

/*
if (!isset($_SESSION['access_token'])) {
    header('Location: login.php');
    exit;
}
*/

$db = getPDO();

include __DIR__ . '/layout/header.php';
include __DIR__ . '/layout/sidebar.php';

$produtos = $db->query("
    SELECT id, nome
    FROM produtos
    WHERE ativo = 1
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

$imagensStmt = $db->prepare("
    SELECT id, url, ordem
    FROM produtos_imagens
    WHERE produto_id = :produto_id
    ORDER BY ordem
");
?>

<div class="main pagina-produto-imagens">

    <!-- TOPO -->
    <div class="topo-pagina">
        <h1>Imagens dos Produtos</h1>
        <p class="instrucoes">💡 Arraste as imagens para reordenar</p>
    </div>

    <!-- GRID DE PRODUTOS -->
    <div class="produtos-grid">

        <?php foreach ($produtos as $produto): ?>

            <?php
                $imagensStmt->execute(['produto_id' => $produto['id']]);
                $imagens = $imagensStmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="produto-card">

                <h3>
                    <?= htmlspecialchars($produto['nome']) ?>
                    <span class="contador-imagens"><?= count($imagens) ?> <?= count($imagens) == 1 ? 'imagem' : 'imagens' ?></span>
                </h3>

                <!-- IMAGENS COM DRAG AND DROP -->
                <div class="imagens-grid" data-produto-id="<?= $produto['id'] ?>">
                    <?php if ($imagens): ?>
                        <?php foreach ($imagens as $img): ?>
                            <div 
                                class="imagem-item" 
                                draggable="true"
                                data-imagem-id="<?= $img['id'] ?>"
                                data-ordem="<?= $img['ordem'] ?>"
                            >
                                <img src="<?= htmlspecialchars($img['url']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>">
                                <div class="ordem-badge"><?= $img['ordem'] ?></div>
                                <button 
                                    class="btn-delete-imagem" 
                                    onclick="excluirImagem(<?= $img['id'] ?>, <?= $produto['id'] ?>)"
                                    title="Excluir imagem"
                                >
                                    🗑️
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="sem-imagem">Sem imagens</div>
                    <?php endif; ?>
                </div>

                <!-- UPLOAD -->
                <form class="upload-form" enctype="multipart/form-data" data-produto-id="<?= $produto['id'] ?>">
                    <input type="hidden" name="produto_id" value="<?= $produto['id'] ?>">
                    <input type="file" name="imagem" accept="image/*" required>
                    <button type="submit" class="btn-primary">
                        📤 Enviar imagem
                    </button>
                </form>

            </div>

        <?php endforeach; ?>

    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>

<script>
// ========================================
// UPLOAD DE IMAGENS VIA AJAX
// ========================================
document.querySelectorAll('.upload-form').forEach(form => {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        const button = form.querySelector('button');
        const fileInput = form.querySelector('input[type="file"]');

        if (!fileInput.files[0]) {
            alert('Selecione uma imagem');
            return;
        }

        button.disabled = true;
        button.textContent = 'Enviando...';

        try {
            const response = await fetch('../api.php?action=upload_imagem_produto', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                alert(data.message || 'Imagem enviada com sucesso!');
                location.reload();
            } else {
                console.error('Erro:', data.message);
                alert(data.message || 'Erro ao enviar imagem');
            }

        } catch (error) {
            console.error('Erro ao enviar imagem:', error);
            alert('Erro ao enviar imagem. Tente novamente.');
        } finally {
            button.disabled = false;
            button.textContent = '📤 Enviar imagem';
        }
    });
});

// ========================================
// DRAG AND DROP PARA REORDENAR IMAGENS
// ========================================
let draggedElement = null;

document.querySelectorAll('.imagem-item').forEach(item => {
    
    // Início do drag
    item.addEventListener('dragstart', function(e) {
        draggedElement = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    // Fim do drag
    item.addEventListener('dragend', function() {
        this.classList.remove('dragging');
        draggedElement = null;
    });

    // Quando passa por cima de outro elemento
    item.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        
        if (draggedElement && draggedElement !== this) {
            const container = this.parentElement;
            const allItems = [...container.querySelectorAll('.imagem-item')];
            const draggedIndex = allItems.indexOf(draggedElement);
            const targetIndex = allItems.indexOf(this);

            if (draggedIndex < targetIndex) {
                container.insertBefore(draggedElement, this.nextSibling);
            } else {
                container.insertBefore(draggedElement, this);
            }
        }
    });

    // Quando solta o elemento
    item.addEventListener('drop', function(e) {
        e.preventDefault();
        salvarNovaOrdem(this.parentElement);
    });
});

// ========================================
// SALVAR NOVA ORDEM NO BANCO
// ========================================
async function salvarNovaOrdem(container) {
    const produtoId = container.dataset.produtoId;
    const items = container.querySelectorAll('.imagem-item');
    
    const novaOrdem = [];
    items.forEach((item, index) => {
        const imagemId = item.dataset.imagemId;
        novaOrdem.push({
            id: imagemId,
            ordem: index + 1
        });
        
        // Atualizar badge visual
        item.querySelector('.ordem-badge').textContent = index + 1;
        item.dataset.ordem = index + 1;
    });

    try {
        const response = await fetch('../api.php?action=reordenar_imagens', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                produto_id: produtoId,
                imagens: novaOrdem
            })
        });

        const data = await response.json();

        if (!data.success) {
            console.error('Erro ao salvar ordem:', data.message);
            alert('Erro ao salvar ordem: ' + (data.message || 'Erro desconhecido'));
            location.reload();
        }

    } catch (error) {
        console.error('Erro ao salvar ordem:', error);
        alert('Erro ao salvar ordem. Recarregando página...');
        location.reload();
    }
}

// ========================================
// EXCLUIR IMAGEM
// ========================================
async function excluirImagem(imagemId, produtoId) {
    if (!confirm('Tem certeza que deseja excluir esta imagem?')) {
        return;
    }

    const formData = new FormData();
    formData.append('id', imagemId);

    try {
        const response = await fetch('../api.php?action=delete_imagem_produto', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert(data.message || 'Imagem excluída com sucesso!');
            location.reload();
        } else {
            console.error('Erro:', data.message);
            alert(data.message || 'Erro ao excluir imagem');
        }

    } catch (error) {
        console.error('Erro ao excluir imagem:', error);
        alert('Erro ao excluir imagem');
    }
}
</script>