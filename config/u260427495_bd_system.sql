-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 24/04/2026 às 14:06
-- Versão do servidor: 11.8.6-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u260427495_bd_system`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `composicoes`
--

CREATE TABLE `composicoes` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `tipo` enum('compra','venda') NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `preco_compra` decimal(10,2) DEFAULT NULL,
  `preco_venda` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `composicoes_itens`
--

CREATE TABLE `composicoes_itens` (
  `id` int(11) NOT NULL,
  `composicao_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `produto_variacao_id` int(11) DEFAULT NULL,
  `quantidade` int(11) NOT NULL CHECK (`quantidade` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `compras`
--

CREATE TABLE `compras` (
  `id` int(11) NOT NULL,
  `fornecedor_id` int(11) DEFAULT NULL,
  `data_compra` timestamp NULL DEFAULT current_timestamp(),
  `valor_total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `compras_itens`
--

CREATE TABLE `compras_itens` (
  `id` int(11) NOT NULL,
  `compra_id` int(11) NOT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `quantidade` int(11) NOT NULL CHECK (`quantidade` > 0),
  `preco_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) GENERATED ALWAYS AS (`quantidade` * `preco_unitario`) STORED,
  `composicao_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `compra_itens_variacoes`
--

CREATE TABLE `compra_itens_variacoes` (
  `id` int(11) NOT NULL,
  `compra_item_id` int(11) NOT NULL,
  `produto_variacao_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL CHECK (`quantidade` > 0),
  `status` enum('pendente','processado') NOT NULL DEFAULT 'pendente',
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `processado_em` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `enderecos`
--

CREATE TABLE `enderecos` (
  `id` int(11) NOT NULL,
  `cep` varchar(9) NOT NULL,
  `rua` varchar(255) NOT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `complemento` varchar(255) DEFAULT NULL,
  `bairro` varchar(100) NOT NULL,
  `cidade` varchar(100) NOT NULL,
  `estado` char(2) NOT NULL,
  `tipo_endereco_id` int(11) NOT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `usuario_id` char(36) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `estoque_atual`
--

CREATE TABLE `estoque_atual` (
  `id_produto_variacao` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `fornecedores`
--

CREATE TABLE `fornecedores` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `cnpj` varchar(18) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_status_pedido`
--

CREATE TABLE `historico_status_pedido` (
  `id` int(11) NOT NULL,
  `venda_id` int(11) NOT NULL,
  `status_pedido_id` int(11) NOT NULL,
  `data_alteracao` timestamp NULL DEFAULT current_timestamp(),
  `observacao` text DEFAULT NULL,
  `usuario_alteracao` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `movimentacoes_estoque`
--

CREATE TABLE `movimentacoes_estoque` (
  `id` int(11) NOT NULL,
  `produto_variacao_id` int(11) NOT NULL,
  `tipo_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `motivo` text DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `venda_item_id` int(11) DEFAULT NULL,
  `compra_itens_variacoes_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `preco_promocional` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos_imagens`
--

CREATE TABLE `produtos_imagens` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `url` text NOT NULL,
  `ordem` int(11) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `produto_variacao_valores`
--

CREATE TABLE `produto_variacao_valores` (
  `id` int(11) NOT NULL,
  `id_produto_variacao` int(11) NOT NULL,
  `id_tipo_variacao` int(11) NOT NULL,
  `id_valor_variacao` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `produto_variacoes`
--

CREATE TABLE `produto_variacoes` (
  `id` int(11) NOT NULL,
  `id_produto` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `status_pagamento`
--

CREATE TABLE `status_pagamento` (
  `id` int(11) NOT NULL,
  `descricao` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `status_pedido`
--

CREATE TABLE `status_pedido` (
  `id` int(11) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `cor` varchar(20) DEFAULT '#6c757d',
  `icone` varchar(10) DEFAULT '?',
  `ordem` int(11) NOT NULL,
  `permite_cancelamento` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tipos_variacao`
--

CREATE TABLE `tipos_variacao` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tipo_endereco`
--

CREATE TABLE `tipo_endereco` (
  `id` int(11) NOT NULL,
  `descricao` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tipo_movimentacao`
--

CREATE TABLE `tipo_movimentacao` (
  `id` int(11) NOT NULL,
  `descricao` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tipo_usuario`
--

CREATE TABLE `tipo_usuario` (
  `id` int(11) NOT NULL,
  `descricao` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` char(36) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo_usuario_id` int(11) NOT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `telefone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `valores_variacao`
--

CREATE TABLE `valores_variacao` (
  `id` int(11) NOT NULL,
  `id_tipo_variacao` int(11) NOT NULL,
  `valor` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `vendas`
--

CREATE TABLE `vendas` (
  `id` int(11) NOT NULL,
  `valor_total` decimal(10,2) NOT NULL,
  `status_pagamento_id` int(11) DEFAULT NULL,
  `data_venda` timestamp NULL DEFAULT current_timestamp(),
  `usuario_id` char(36) NOT NULL,
  `status_pedido_id` int(11) DEFAULT 1,
  `endereco_id` int(11) DEFAULT NULL,
  `abacatepay_billing_id` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `vendas_itens`
--

CREATE TABLE `vendas_itens` (
  `id` int(11) NOT NULL,
  `venda_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL CHECK (`quantidade` > 0),
  `preco_unitario_original` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) GENERATED ALWAYS AS (`quantidade` * `preco_unitario_original`) STORED,
  `valor_desconto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `preco_unitario_final` decimal(10,2) NOT NULL,
  `produto_variacao_id` int(11) DEFAULT NULL,
  `composicao_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `composicoes`
--
ALTER TABLE `composicoes`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `composicoes_itens`
--
ALTER TABLE `composicoes_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ci_composicao` (`composicao_id`),
  ADD KEY `fk_ci_produto` (`produto_id`),
  ADD KEY `fk_ci_variacao` (`produto_variacao_id`);

--
-- Índices de tabela `compras`
--
ALTER TABLE `compras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_compras_fornecedor` (`fornecedor_id`),
  ADD KEY `idx_compras_data` (`data_compra`);

--
-- Índices de tabela `compras_itens`
--
ALTER TABLE `compras_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `compras_itens_compra_id_fkey` (`compra_id`),
  ADD KEY `fk_compras_itens_composicao` (`composicao_id`),
  ADD KEY `compras_itens_produto_id_fkey` (`produto_id`);

--
-- Índices de tabela `compra_itens_variacoes`
--
ALTER TABLE `compra_itens_variacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_civ_compra_item` (`compra_item_id`),
  ADD KEY `fk_civ_produto_variacao` (`produto_variacao_id`);

--
-- Índices de tabela `enderecos`
--
ALTER TABLE `enderecos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `enderecos_tipo_endereco_id_fkey` (`tipo_endereco_id`),
  ADD KEY `idx_enderecos_usuario` (`usuario_id`);

--
-- Índices de tabela `estoque_atual`
--
ALTER TABLE `estoque_atual`
  ADD PRIMARY KEY (`id_produto_variacao`);

--
-- Índices de tabela `fornecedores`
--
ALTER TABLE `fornecedores`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `historico_status_pedido`
--
ALTER TABLE `historico_status_pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_historico_status` (`status_pedido_id`),
  ADD KEY `fk_historico_usuario` (`usuario_alteracao`),
  ADD KEY `idx_historico_venda` (`venda_id`);

--
-- Índices de tabela `movimentacoes_estoque`
--
ALTER TABLE `movimentacoes_estoque`
  ADD PRIMARY KEY (`id`),
  ADD KEY `movimentacoes_estoque_produto_variacao_fk` (`produto_variacao_id`),
  ADD KEY `movimentacoes_estoque_tipo_fk` (`tipo_id`),
  ADD KEY `fk_mov_venda_item` (`venda_item_id`),
  ADD KEY `fk_mov_compra_itens_variacoes` (`compra_itens_variacoes_id`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_produtos_categoria` (`categoria_id`),
  ADD KEY `idx_produtos_ativo` (`ativo`);

--
-- Índices de tabela `produtos_imagens`
--
ALTER TABLE `produtos_imagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produtos_imagens_produto_id_fkey` (`produto_id`);

--
-- Índices de tabela `produto_variacao_valores`
--
ALTER TABLE `produto_variacao_valores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_variacao_valores_id_produto_variacao_fkey` (`id_produto_variacao`),
  ADD KEY `produto_variacao_valores_id_tipo_variacao_fkey` (`id_tipo_variacao`),
  ADD KEY `produto_variacao_valores_id_valor_variacao_fkey` (`id_valor_variacao`);

--
-- Índices de tabela `produto_variacoes`
--
ALTER TABLE `produto_variacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_variacoes_id_produto_fkey` (`id_produto`);

--
-- Índices de tabela `status_pagamento`
--
ALTER TABLE `status_pagamento`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `status_pedido`
--
ALTER TABLE `status_pedido`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `ordem` (`ordem`);

--
-- Índices de tabela `tipos_variacao`
--
ALTER TABLE `tipos_variacao`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Índices de tabela `tipo_endereco`
--
ALTER TABLE `tipo_endereco`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `tipo_movimentacao`
--
ALTER TABLE `tipo_movimentacao`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `tipo_usuario`
--
ALTER TABLE `tipo_usuario`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_tipo_usuario` (`tipo_usuario_id`);

--
-- Índices de tabela `valores_variacao`
--
ALTER TABLE `valores_variacao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `valores_variacao_id_tipo_variacao_fkey` (`id_tipo_variacao`);

--
-- Índices de tabela `vendas`
--
ALTER TABLE `vendas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendas_status_pagamento_id_fkey` (`status_pagamento_id`),
  ADD KEY `fk_vendas_status_pedido` (`status_pedido_id`),
  ADD KEY `idx_vendas_usuario` (`usuario_id`),
  ADD KEY `idx_vendas_data` (`data_venda`),
  ADD KEY `fk_vendas_endereco` (`endereco_id`),
  ADD KEY `idx_vendas_billing` (`abacatepay_billing_id`);

--
-- Índices de tabela `vendas_itens`
--
ALTER TABLE `vendas_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendas_itens_venda_id_fkey` (`venda_id`),
  ADD KEY `vendas_itens_produto_variacao_fk` (`produto_variacao_id`),
  ADD KEY `fk_vendas_itens_composicao` (`composicao_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `composicoes`
--
ALTER TABLE `composicoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `composicoes_itens`
--
ALTER TABLE `composicoes_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `compras`
--
ALTER TABLE `compras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `compras_itens`
--
ALTER TABLE `compras_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `compra_itens_variacoes`
--
ALTER TABLE `compra_itens_variacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `enderecos`
--
ALTER TABLE `enderecos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `fornecedores`
--
ALTER TABLE `fornecedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `historico_status_pedido`
--
ALTER TABLE `historico_status_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `movimentacoes_estoque`
--
ALTER TABLE `movimentacoes_estoque`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `produtos_imagens`
--
ALTER TABLE `produtos_imagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `produto_variacao_valores`
--
ALTER TABLE `produto_variacao_valores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `produto_variacoes`
--
ALTER TABLE `produto_variacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tipos_variacao`
--
ALTER TABLE `tipos_variacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `valores_variacao`
--
ALTER TABLE `valores_variacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `vendas`
--
ALTER TABLE `vendas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `vendas_itens`
--
ALTER TABLE `vendas_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `composicoes_itens`
--
ALTER TABLE `composicoes_itens`
  ADD CONSTRAINT `fk_ci_composicao` FOREIGN KEY (`composicao_id`) REFERENCES `composicoes` (`id`),
  ADD CONSTRAINT `fk_ci_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  ADD CONSTRAINT `fk_ci_variacao` FOREIGN KEY (`produto_variacao_id`) REFERENCES `produto_variacoes` (`id`);

--
-- Restrições para tabelas `compras`
--
ALTER TABLE `compras`
  ADD CONSTRAINT `compras_fornecedor_id_fkey` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`);

--
-- Restrições para tabelas `compras_itens`
--
ALTER TABLE `compras_itens`
  ADD CONSTRAINT `compras_itens_compra_id_fkey` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`),
  ADD CONSTRAINT `compras_itens_produto_id_fkey` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  ADD CONSTRAINT `fk_compras_itens_composicao` FOREIGN KEY (`composicao_id`) REFERENCES `composicoes` (`id`);

--
-- Restrições para tabelas `compra_itens_variacoes`
--
ALTER TABLE `compra_itens_variacoes`
  ADD CONSTRAINT `fk_civ_compra_item` FOREIGN KEY (`compra_item_id`) REFERENCES `compras_itens` (`id`),
  ADD CONSTRAINT `fk_civ_produto_variacao` FOREIGN KEY (`produto_variacao_id`) REFERENCES `produto_variacoes` (`id`);

--
-- Restrições para tabelas `enderecos`
--
ALTER TABLE `enderecos`
  ADD CONSTRAINT `enderecos_tipo_endereco_id_fkey` FOREIGN KEY (`tipo_endereco_id`) REFERENCES `tipo_endereco` (`id`),
  ADD CONSTRAINT `fk_enderecos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `estoque_atual`
--
ALTER TABLE `estoque_atual`
  ADD CONSTRAINT `estoque_atual_id_produto_variacao_fkey` FOREIGN KEY (`id_produto_variacao`) REFERENCES `produto_variacoes` (`id`);

--
-- Restrições para tabelas `historico_status_pedido`
--
ALTER TABLE `historico_status_pedido`
  ADD CONSTRAINT `fk_historico_status` FOREIGN KEY (`status_pedido_id`) REFERENCES `status_pedido` (`id`),
  ADD CONSTRAINT `fk_historico_usuario` FOREIGN KEY (`usuario_alteracao`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_historico_venda` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`);

--
-- Restrições para tabelas `movimentacoes_estoque`
--
ALTER TABLE `movimentacoes_estoque`
  ADD CONSTRAINT `fk_mov_compra_itens_variacoes` FOREIGN KEY (`compra_itens_variacoes_id`) REFERENCES `compra_itens_variacoes` (`id`),
  ADD CONSTRAINT `fk_mov_venda_item` FOREIGN KEY (`venda_item_id`) REFERENCES `vendas_itens` (`id`),
  ADD CONSTRAINT `movimentacoes_estoque_produto_variacao_fk` FOREIGN KEY (`produto_variacao_id`) REFERENCES `produto_variacoes` (`id`),
  ADD CONSTRAINT `movimentacoes_estoque_tipo_fk` FOREIGN KEY (`tipo_id`) REFERENCES `tipo_movimentacao` (`id`);

--
-- Restrições para tabelas `produtos`
--
ALTER TABLE `produtos`
  ADD CONSTRAINT `produtos_categoria_id_fkey` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`);

--
-- Restrições para tabelas `produtos_imagens`
--
ALTER TABLE `produtos_imagens`
  ADD CONSTRAINT `produtos_imagens_produto_id_fkey` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);

--
-- Restrições para tabelas `produto_variacao_valores`
--
ALTER TABLE `produto_variacao_valores`
  ADD CONSTRAINT `produto_variacao_valores_id_produto_variacao_fkey` FOREIGN KEY (`id_produto_variacao`) REFERENCES `produto_variacoes` (`id`),
  ADD CONSTRAINT `produto_variacao_valores_id_tipo_variacao_fkey` FOREIGN KEY (`id_tipo_variacao`) REFERENCES `tipos_variacao` (`id`),
  ADD CONSTRAINT `produto_variacao_valores_id_valor_variacao_fkey` FOREIGN KEY (`id_valor_variacao`) REFERENCES `valores_variacao` (`id`);

--
-- Restrições para tabelas `produto_variacoes`
--
ALTER TABLE `produto_variacoes`
  ADD CONSTRAINT `produto_variacoes_id_produto_fkey` FOREIGN KEY (`id_produto`) REFERENCES `produtos` (`id`);

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_tipo_usuario` FOREIGN KEY (`tipo_usuario_id`) REFERENCES `tipo_usuario` (`id`);

--
-- Restrições para tabelas `valores_variacao`
--
ALTER TABLE `valores_variacao`
  ADD CONSTRAINT `valores_variacao_id_tipo_variacao_fkey` FOREIGN KEY (`id_tipo_variacao`) REFERENCES `tipos_variacao` (`id`);

--
-- Restrições para tabelas `vendas`
--
ALTER TABLE `vendas`
  ADD CONSTRAINT `fk_vendas_endereco` FOREIGN KEY (`endereco_id`) REFERENCES `enderecos` (`id`),
  ADD CONSTRAINT `fk_vendas_status_pedido` FOREIGN KEY (`status_pedido_id`) REFERENCES `status_pedido` (`id`),
  ADD CONSTRAINT `fk_vendas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `vendas_status_pagamento_id_fkey` FOREIGN KEY (`status_pagamento_id`) REFERENCES `status_pagamento` (`id`);

--
-- Restrições para tabelas `vendas_itens`
--
ALTER TABLE `vendas_itens`
  ADD CONSTRAINT `fk_vendas_itens_composicao` FOREIGN KEY (`composicao_id`) REFERENCES `composicoes` (`id`),
  ADD CONSTRAINT `vendas_itens_produto_variacao_fk` FOREIGN KEY (`produto_variacao_id`) REFERENCES `produto_variacoes` (`id`),
  ADD CONSTRAINT `vendas_itens_venda_id_fkey` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
