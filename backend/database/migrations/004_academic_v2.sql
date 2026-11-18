-- Recursos acadêmicos da V2: notas, comunicados e redefinição de senha.
-- Executar depois das migrações 001, 002 e 003.
USE classmanager_v2;

CREATE TABLE IF NOT EXISTS notas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  matricula_id BIGINT UNSIGNED NOT NULL,
  titulo VARCHAR(120) NOT NULL,
  nota DECIMAL(5,2) NOT NULL,
  nota_maxima DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  data_avaliacao DATE NOT NULL,
  observacoes VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notas_matricula_data (matricula_id, data_avaliacao),
  CONSTRAINT fk_notas_matricula FOREIGN KEY (matricula_id)
    REFERENCES matriculas (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_notas_valor CHECK (nota >= 0 AND nota <= nota_maxima AND nota_maxima > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS comunicados (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(160) NOT NULL,
  mensagem TEXT NOT NULL,
  publico ENUM('todos','alunos','professores') NOT NULL DEFAULT 'todos',
  turma_id BIGINT UNSIGNED NULL,
  professor_destinatario_id BIGINT UNSIGNED NULL,
  publicar_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_em DATETIME NULL,
  autor_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_comunicados_publicacao (publicar_em, expira_em),
  CONSTRAINT fk_comunicados_autor FOREIGN KEY (autor_id)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_comunicados_turma FOREIGN KEY (turma_id)
    REFERENCES turmas (id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_comunicados_professor FOREIGN KEY (professor_destinatario_id)
    REFERENCES professores (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recuperacao_senhas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira_em DATETIME NOT NULL,
  usado_em DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recuperacao_token (token_hash),
  KEY idx_recuperacao_usuario (usuario_id, expira_em),
  CONSTRAINT fk_recuperacao_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- MySQL 8+ aceita este campo para registrar substituição por aula.
ALTER TABLE aulas ADD COLUMN professor_substituto_id BIGINT UNSIGNED NULL;
ALTER TABLE aulas ADD CONSTRAINT fk_aulas_professor_substituto FOREIGN KEY (professor_substituto_id)
  REFERENCES professores (id) ON UPDATE CASCADE ON DELETE SET NULL;
