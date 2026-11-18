# ClassManager

Sistema demonstrativo de gestão para pequenas escolas de idiomas. O projeto começa do zero e utiliza apenas dados fictícios. A publicação no GitHub será feita pelo integrante responsável; hospedagem externa será decidida pelo grupo em uma etapa futura.

## ClassManager V2

Esta pasta é uma cópia independente da versão original. A V2 preserva as telas e módulos anteriores e acrescenta notas, calendário acadêmico semanal, comunicados, redefinição de senha e substituição de professores por aula. Ela usa o banco `classmanager_v2`; o banco da versão original não é alterado.

## Tecnologias

- Frontend: HTML5, CSS3, JavaScript puro, Bootstrap 5, Lucide Icons e Chart.js.
- Backend: PHP 8.2+, API HTTP e PDO.
- Banco de dados: MySQL.

Bootstrap, Lucide e Chart.js são carregados por CDN. A API utiliza sessão PHP, token CSRF, PDO e permissões verificadas no servidor.

## Estrutura

```text
frontend/                  Interface web
  css/                     Estilos
  js/                      JavaScript da interface
  pages/                   Páginas dos módulos
  assets/                  Imagens e outros recursos
backend/
  config/                  Configurações e conexão
  controllers/             Entrada e resposta das operações
  database/migrations/     Scripts SQL versionados
  database/seeds/          Dados demonstrativos
  middlewares/             Autenticação e permissões
  models/                  Consultas e acesso a dados
  public/                  Único diretório público do backend
  routes/                  Rotas da API
  services/                Regras de negócio
  utils/                   Funções compartilhadas
```

## Requisitos para desenvolvimento

- XAMPP, com PHP e MySQL/MariaDB.
- Navegador moderno e editor de código (VS Code recomendado).
- Git para controle de versão.

## Preparar o XAMPP

1. Abra `C:\xampp\xampp-control.exe` e inicie **MySQL**. Inicie também **Apache** se quiser usar o phpMyAdmin.
2. No navegador, abra `http://localhost/phpmyadmin` e importe, nesta ordem, `backend/database/migrations/001_create_students.sql`, `backend/database/migrations/002_create_school_modules.sql`, `backend/database/migrations/003_student_portal.sql` e `backend/database/migrations/004_academic_v2.sql`. A última migração cria notas, comunicados, recuperação de senha e substituições. Execute cada migração uma vez. Também é possível executar os arquivos no Workbench conectado ao MySQL do XAMPP.
3. Copie `.env.example` para `.env` nesta pasta, se ainda não tiver esse arquivo. Na instalação padrão do XAMPP, use `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_NAME=classmanager_v2`, `DB_USER=root` e `DB_PASSWORD=` (vazio). Se definiu uma senha para o usuário root do XAMPP, coloque-a em `DB_PASSWORD`.
4. Escolha `SEED_ADMIN_EMAIL`, `SEED_ADMIN_PASSWORD` e `SEED_DEMO_PASSWORD` no `.env`. As duas senhas devem ter pelo menos 10 caracteres e não devem ser enviadas ao GitHub.
5. Abra o PowerShell dentro de `ClassManagerV2` e execute o gerador fictício. Ele cria ou atualiza as contas demonstrativas, professores, cursos, turmas, alunos, matrículas, aulas, frequências, mensalidades, pagamentos, notas e comunicado de exemplo no banco V2. Executá-lo novamente sincroniza as senhas demonstrativas com o `.env` e preserva os dados existentes.

   ```powershell
   C:\xampp\php\php.exe backend/database/seeds/seed_demo.php
   ```

6. Abra o PowerShell nesta pasta `ClassManagerV2` e inicie o servidor usando o PHP incluído no XAMPP:

   ```powershell
   C:\xampp\php\php.exe -S localhost:8000
   ```

7. Abra `http://localhost:8000/frontend/login.html`. A conta principal de administrador usa `SEED_ADMIN_EMAIL` e `SEED_ADMIN_PASSWORD`. As demais contas usam `SEED_DEMO_PASSWORD`: administrador `admin2@classmanager.test`; secretaria `secretaria@classmanager.test` e `secretaria2@classmanager.test`; professores `professora1@classmanager.test`, `professor@classmanager.test` e `professora3@classmanager.test`; alunos `aluno1@example.test` até `aluno12@example.test`. Cada perfil é encaminhado à sua área e as permissões são verificadas no backend.

Se o MySQL do XAMPP não iniciar por conflito na porta 3306, outro serviço MySQL pode estar ocupando essa porta. `.env` é ignorado pelo Git: nunca envie esse arquivo ou uma senha ao repositório.

## Módulos disponíveis

- Dashboard com totais, aulas próximas, atividades recentes e gráfico de recebimentos.
- Alunos e responsáveis, com pesquisa, status, histórico e validação de CPF, e-mail, telefone e datas.
- Professores, cursos, turmas, matrículas e usuários demonstrativos.
- Aulas e frequência individual por aluno. Choques de horário de um mesmo professor são recusados.
- Mensalidades e pagamentos em tabelas separadas, com saldo, atraso e registro de pagamentos parciais.
- Perfis de administrador, secretaria, professor e aluno. A área do aluno mostra somente os próprios cursos, aulas, frequência e financeiro; as permissões são verificadas na API.
- Notas por matrícula, lançadas por administradores e professores das próprias turmas, com visualização restrita ao aluno correspondente.
- Calendário semanal que combina aulas cadastradas com os horários recorrentes das turmas. Professores e alunos veem somente as turmas às quais estão vinculados.
- Comunicados institucionais para todos, alunos ou professores. Alterações em aulas e turmas criam avisos direcionados às matrículas daquela turma.
- Substituição de professor em uma aula, disponível à administração e refletida no calendário e nos avisos.
- Redefinição de senha com token de uso único e validade de 30 minutos. Em `APP_ENV=development`, a tela mostra o token para facilitar a demonstração. Para envio por e-mail, configure `MAIL_FROM` e o `mail()` do PHP no servidor.
- O administrador pode criar contas vinculadas aos cadastros de professores e alunos em **Usuários e permissões**. Cada conta individual precisa de e-mail, senha e vínculo ao cadastro correspondente.

Use somente dados fictícios. O servidor embutido do PHP serve a interface e a API para desenvolvimento local; para acesso externo, será preciso publicar o PHP e o MySQL em serviços compatíveis, decisão que o grupo tomará depois.
