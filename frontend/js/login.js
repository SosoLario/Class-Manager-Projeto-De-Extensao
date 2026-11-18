document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) window.lucide.createIcons();
  const form = document.querySelector('#loginForm');
  const errorBox = document.querySelector('#loginError');
  const button = document.querySelector('#loginSubmit');

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorBox.textContent = '';
    button.disabled = true;
    button.textContent = 'Entrando…';
    try {
      const values = new FormData(form);
      const result = await ClassManagerApi.login(String(values.get('email')).trim(), String(values.get('password')));
      window.location.assign(result.data.perfil === 'aluno' ? 'pages/area-aluno.html' : 'index.html');
    } catch (error) {
      errorBox.textContent = error.message;
    } finally {
      button.disabled = false;
      button.innerHTML = 'Entrar <i data-lucide="arrow-right"></i>';
      if (window.lucide) window.lucide.createIcons();
    }
  });
});
