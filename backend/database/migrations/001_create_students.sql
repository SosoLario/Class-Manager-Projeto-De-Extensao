-- Base do módulo de alunos e responsáveis legais.
-- Execute uma única vez no banco classmanager pelo MySQL Workbench.
CREATE DATABASE IF NOT EXISTS classmanager_v2
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE classmanager_v2;

CREATE TABLE IF NOT EXISTS responsaveis (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome_completo VARCHAR(160) NOT NULL,
  telefone VARCHAR(30) NULL,
  email VARCHAR(190) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS alunos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  responsavel_id BIGINT UNSIGNED NULL,
  nome_completo VARCHAR(160) NOT NULL,
  cpf CHAR(11) NULL,
  data_nascimento DATE NULL,
  telefone VARCHAR(30) NULL,
  email VARCHAR(190) NULL,
  endereco VARCHAR(255) NULL,
  data_matricula DATE NOT NULL,
  situacao ENUM('ativo', 'inativo', 'trancado') NOT NULL DEFAULT 'ativo',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_alunos_cpf (cpf),
  KEY idx_alunos_nome (nome_completo),
  KEY idx_alunos_situacao (situacao),
  CONSTRAINT fk_alunos_responsaveis FOREIGN KEY (responsavel_id)
    REFERENCES responsaveis (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;
