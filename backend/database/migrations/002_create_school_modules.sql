-- Módulos acadêmico, financeiro e de acesso do ClassManager.
-- Execute depois de 001_create_students.sql, no banco classmanager.
USE classmanager_v2;

CREATE TABLE IF NOT EXISTS usuarios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  senha_hash VARCHAR(255) NOT NULL,
  perfil ENUM('administrador', 'professor', 'secretaria') NOT NULL DEFAULT 'secretaria',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email),
  KEY idx_usuarios_perfil (perfil, ativo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS professores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id BIGINT UNSIGNED NULL,
  nome_completo VARCHAR(160) NOT NULL,
  telefone VARCHAR(30) NULL,
  email VARCHAR(190) NULL,
  idiomas VARCHAR(255) NOT NULL DEFAULT '',
  situacao ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_professores_usuario (usuario_id),
  KEY idx_professores_nome (nome_completo),
  CONSTRAINT fk_professores_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cursos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(120) NOT NULL,
  idioma VARCHAR(60) NOT NULL,
  nivel ENUM('básico', 'intermediário', 'avançado') NOT NULL,
  duracao_meses SMALLINT UNSIGNED NOT NULL,
  carga_horaria SMALLINT UNSIGNED NOT NULL,
  valor DECIMAL(10,2) NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cursos_idioma_nivel (idioma, nivel),
  KEY idx_cursos_ativo (ativo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS turmas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(100) NOT NULL,
  curso_id BIGINT UNSIGNED NOT NULL,
  professor_id BIGINT UNSIGNED NOT NULL,
  capacidade SMALLINT UNSIGNED NOT NULL,
  data_inicio DATE NOT NULL,
  data_fim DATE NULL,
  situacao ENUM('ativa', 'concluída', 'cancelada') NOT NULL DEFAULT 'ativa',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_turmas_professor (professor_id, situacao),
  KEY idx_turmas_curso (curso_id),
  CONSTRAINT fk_turmas_curso FOREIGN KEY (curso_id)
    REFERENCES cursos (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_turmas_professor FOREIGN KEY (professor_id)
    REFERENCES professores (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS turma_horarios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  turma_id BIGINT UNSIGNED NOT NULL,
  dia_semana TINYINT UNSIGNED NOT NULL COMMENT '1=segunda-feira até 7=domingo',
  hora_inicio TIME NOT NULL,
  hora_fim TIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_turma_dia_horario (turma_id, dia_semana),
  KEY idx_horarios_conflito (dia_semana, hora_inicio, hora_fim),
  CONSTRAINT fk_horarios_turma FOREIGN KEY (turma_id)
    REFERENCES turmas (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS matriculas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  aluno_id BIGINT UNSIGNED NOT NULL,
  turma_id BIGINT UNSIGNED NOT NULL,
  data_matricula DATE NOT NULL,
  valor_mensalidade DECIMAL(10,2) NOT NULL,
  situacao ENUM('ativa', 'concluída', 'cancelada', 'transferida') NOT NULL DEFAULT 'ativa',
  observacoes VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_matriculas_aluno (aluno_id, situacao),
  KEY idx_matriculas_turma (turma_id, situacao),
  CONSTRAINT fk_matriculas_aluno FOREIGN KEY (aluno_id)
    REFERENCES alunos (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_matriculas_turma FOREIGN KEY (turma_id)
    REFERENCES turmas (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS aulas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  turma_id BIGINT UNSIGNED NOT NULL,
  data_aula DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fim TIME NOT NULL,
  conteudo VARCHAR(1000) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_aulas_turma_data (turma_id, data_aula),
  CONSTRAINT fk_aulas_turma FOREIGN KEY (turma_id)
    REFERENCES turmas (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS frequencias (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  aula_id BIGINT UNSIGNED NOT NULL,
  aluno_id BIGINT UNSIGNED NOT NULL,
  situacao ENUM('presente', 'falta', 'atrasado') NOT NULL,
  observacoes VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_frequencia_aula_aluno (aula_id, aluno_id),
  KEY idx_frequencia_aluno (aluno_id),
  CONSTRAINT fk_frequencias_aula FOREIGN KEY (aula_id)
    REFERENCES aulas (id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_frequencias_aluno FOREIGN KEY (aluno_id)
    REFERENCES alunos (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mensalidades (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  matricula_id BIGINT UNSIGNED NOT NULL,
  referencia CHAR(7) NOT NULL COMMENT 'Formato YYYY-MM',
  valor DECIMAL(10,2) NOT NULL,
  desconto DECIMAL(10,2) NOT NULL DEFAULT 0,
  multa DECIMAL(10,2) NOT NULL DEFAULT 0,
  juros DECIMAL(10,2) NOT NULL DEFAULT 0,
  vencimento DATE NOT NULL,
  situacao ENUM('pendente', 'pago', 'atrasado', 'cancelado') NOT NULL DEFAULT 'pendente',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mensalidade_matricula_referencia (matricula_id, referencia),
  KEY idx_mensalidades_vencimento_situacao (vencimento, situacao),
  CONSTRAINT fk_mensalidades_matricula FOREIGN KEY (matricula_id)
    REFERENCES matriculas (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pagamentos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mensalidade_id BIGINT UNSIGNED NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  data_pagamento DATETIME NOT NULL,
  forma_pagamento ENUM('dinheiro', 'pix', 'cartão', 'transferência', 'outro') NOT NULL DEFAULT 'outro',
  observacoes VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pagamentos_mensalidade_data (mensalidade_id, data_pagamento),
  CONSTRAINT fk_pagamentos_mensalidade FOREIGN KEY (mensalidade_id)
    REFERENCES mensalidades (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;
