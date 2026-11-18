-- Portal do aluno e vínculo entre aluno e conta de acesso.
-- Execute uma vez, depois das migrações 001 e 002, no banco classmanager.
USE classmanager_v2;

ALTER TABLE usuarios
  MODIFY perfil ENUM('administrador', 'professor', 'secretaria', 'aluno') NOT NULL DEFAULT 'secretaria';

ALTER TABLE alunos
  ADD COLUMN usuario_id BIGINT UNSIGNED NULL UNIQUE AFTER id,
  ADD CONSTRAINT fk_alunos_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL;
