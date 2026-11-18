document.addEventListener('DOMContentLoaded', () => {
  const modalElement = document.querySelector('#studentModal');
  const modal = new bootstrap.Modal(modalElement);
  const form = document.querySelector('#studentForm');
  const tbody = document.querySelector('#studentsTableBody');
  const message = document.querySelector('#tableMessage');
  const toast = document.querySelector('#toastMessage');
  const submitButton = document.querySelector('#saveStudent');
  let editingId = null;
  let toastTimer;
  let searchTimer;

  if (window.lucide) window.lucide.createIcons();
  document.querySelector('#todayLabel').textContent = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit', month: 'long', year: 'numeric',
  }).format(new Date());

  const sidebar = document.querySelector('#sidebar');
  const scrim = document.querySelector('#sidebarScrim');
  const menuToggle = document.querySelector('#menuToggle');
  const closeMenu = () => {
    sidebar.classList.remove('open');
    scrim.classList.remove('visible');
    menuToggle.setAttribute('aria-expanded', 'false');
  };
  menuToggle.addEventListener('click', () => {
    const open = sidebar.classList.toggle('open');
    scrim.classList.toggle('visible', open);
    menuToggle.setAttribute('aria-expanded', String(open));
  });
  scrim.addEventListener('click', closeMenu);

  function notify(text) {
    toast.textContent = text;
    toast.classList.add('show');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => toast.classList.remove('show'), 3000);
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[character]);
  }

  function formatDate(value) {
    if (!value) return '—';
    const [year, month, day] = value.slice(0, 10).split('-');
    return `${day}/${month}/${year}`;
  }

  function initials(name) {
    return String(name || '?').trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
  }

  function formatPhone(value) {
    const digits = String(value || '').replace(/\D/g, '').slice(0, 11);
    if (!digits) return 'Telefone não informado';
    if (digits.length <= 2) return `(${digits}`;
    if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}${digits.length > 6 ? `-${digits.slice(6, 10)}` : ''}`;
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
  }

  function formatCpf(value) {
    const digits = String(value || '').replace(/\D/g, '').slice(0, 11);
    return digits.length === 11 ? digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : digits;
  }

  function renderRows(students) {
    document.querySelector('#studentCount').textContent = students.length;
    if (students.length === 0) {
      tbody.innerHTML = '<tr class="empty-row"><td colspan="5">Nenhum aluno encontrado. Cadastre um aluno para começar.</td></tr>';
      return;
    }
    tbody.innerHTML = students.map((student) => {
      const statusLabels = { ativo: 'Ativo', inativo: 'Inativo', trancado: 'Trancado' };
      return `<tr>
        <td><div class="student-cell"><span class="student-avatar">${escapeHtml(initials(student.nome_completo))}</span><span class="student-name"><strong>${escapeHtml(student.nome_completo)}</strong><small>${student.cpf ? `CPF ${escapeHtml(formatCpf(student.cpf))}` : 'CPF não informado'}</small></span></div></td>
        <td><span class="contact-cell"><span>${escapeHtml(formatPhone(student.telefone))}</span><small>${escapeHtml(student.email || 'E-mail não informado')}</small></span></td>
        <td>${formatDate(student.data_matricula)}</td>
        <td><span class="status-pill status-${escapeHtml(student.situacao)}">${statusLabels[student.situacao] || '—'}</span></td>
        <td><div class="row-actions"><button type="button" class="row-action edit" data-id="${Number(student.id)}" aria-label="Editar ${escapeHtml(student.nome_completo)}" title="Editar"><i data-lucide="pencil"></i></button><button type="button" class="row-action archive" data-id="${Number(student.id)}" aria-label="Arquivar ${escapeHtml(student.nome_completo)}" title="Arquivar"><i data-lucide="archive"></i></button></div></td>
      </tr>`;
    }).join('');
    if (window.lucide) window.lucide.createIcons();
  }

  async function loadStudents() {
    message.textContent = 'Carregando alunos…';
    const params = new URLSearchParams();
    const search = document.querySelector('#searchInput').value.trim();
    const status = document.querySelector('#statusFilter').value;
    if (search) params.set('search', search);
    if (status) params.set('status', status);
    try {
      const result = await ClassManagerApi.request(`students?${params.toString()}`);
      message.textContent = '';
      renderRows(result.data || []);
    } catch (error) {
      document.querySelector('#studentCount').textContent = '—';
      tbody.innerHTML = '';
      message.textContent = `${error.message} Verifique se a API está em execução e se o MySQL está configurado.`;
    }
  }

  function clearValidation() {
    form.querySelectorAll('.is-invalid').forEach((field) => field.classList.remove('is-invalid'));
    form.querySelectorAll('.field-error').forEach((field) => { field.textContent = ''; });
    const formError = form.querySelector('.student-form-error');
    if (formError) formError.remove();
  }

  function setFieldError(name, text) {
    const field = form.elements.namedItem(name);
    if (!field) return;
    field.classList.add('is-invalid');
    const error = field.closest('.form-field')?.querySelector('.field-error');
    if (error) error.textContent = text;
  }

  function showFormError(text) {
    let error = form.querySelector('.student-form-error');
    if (!error) {
      error = document.createElement('p');
      error.className = 'student-form-error';
      form.querySelector('.modal-body').prepend(error);
    }
    error.textContent = text;
  }

  function dateInputValue(value) {
    return value ? String(value).slice(0, 10) : '';
  }

  function fillForm(student = {}) {
    form.elements.nome_completo.value = student.nome_completo || '';
    form.elements.cpf.value = formatCpf(student.cpf || '');
    form.elements.data_nascimento.value = dateInputValue(student.data_nascimento);
    form.elements.telefone.value = student.telefone ? formatPhone(student.telefone) : '';
    form.elements.email.value = student.email || '';
    form.elements.endereco.value = student.endereco || '';
    const now = new Date();
    const localToday = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    form.elements.data_matricula.value = dateInputValue(student.data_matricula) || localToday;
    form.elements.situacao.value = student.situacao || 'ativo';
    form.elements.responsavel_nome.value = student.responsavel_nome || '';
    form.elements.responsavel_telefone.value = student.responsavel_telefone ? formatPhone(student.responsavel_telefone) : '';
    form.elements.responsavel_email.value = student.responsavel_email || '';
  }

  document.querySelector('#newStudent').addEventListener('click', () => {
    editingId = null;
    form.reset();
    clearValidation();
    fillForm();
    document.querySelector('#studentModalTitle').textContent = 'Novo aluno';
    modal.show();
  });

  document.querySelector('#searchInput').addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(loadStudents, 250);
  });
  document.querySelector('#statusFilter').addEventListener('change', loadStudents);

  document.querySelector('#studentsTableBody').addEventListener('click', async (event) => {
    const editButton = event.target.closest('.edit');
    const archiveButton = event.target.closest('.archive');
    if (editButton) {
      try {
        const result = await ClassManagerApi.request(`students/${editButton.dataset.id}`);
        editingId = Number(editButton.dataset.id);
        clearValidation();
        fillForm(result.data);
        document.querySelector('#studentModalTitle').textContent = 'Editar aluno';
        modal.show();
      } catch (error) { notify(error.message); }
    }
    if (archiveButton) {
      const row = archiveButton.closest('tr');
      const studentName = row.querySelector('.student-name strong').textContent;
      if (!window.confirm(`Arquivar o cadastro de ${studentName}? Ele será marcado como inativo e permanecerá no histórico.`)) return;
      try {
        const result = await ClassManagerApi.request(`students/${archiveButton.dataset.id}`, { method: 'DELETE', body: {} });
        notify(result.message);
        await loadStudents();
      } catch (error) { notify(error.message); }
    }
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearValidation();
    if (!form.reportValidity()) return;

    const payload = Object.fromEntries(new FormData(form).entries());
    payload.cpf = payload.cpf.replace(/\D/g, '');
    submitButton.disabled = true;
    submitButton.textContent = 'Salvando…';
    try {
      const result = await ClassManagerApi.request(`students${editingId ? `/${editingId}` : ''}`, {
        method: editingId ? 'PUT' : 'POST', body: payload,
      });
      modal.hide();
      notify(editingId ? 'Cadastro do aluno atualizado.' : 'Aluno cadastrado com sucesso.');
      await loadStudents();
    } catch (error) {
      if (error.status === 422 && error.fields) {
        Object.entries(error.fields).forEach(([field, text]) => setFieldError(field, text));
        return;
      }
      showFormError(error.message);
    } finally {
      submitButton.disabled = false;
      submitButton.innerHTML = '<i data-lucide="check"></i> Salvar aluno';
      if (window.lucide) window.lucide.createIcons();
    }
  });

  modalElement.addEventListener('hidden.bs.modal', clearValidation);
  form.elements.cpf.addEventListener('input', (event) => {
    const digits = event.target.value.replace(/\D/g, '').slice(0, 11);
    event.target.value = digits.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  });
  form.querySelectorAll('input[type="tel"]').forEach((input) => input.addEventListener('input', (event) => {
    const digits = event.target.value.replace(/\D/g, '').slice(0, 11);
    if (!digits) { event.target.value = ''; return; }
    event.target.value = digits.length <= 10
      ? `(${digits.slice(0,2)}${digits.length > 2 ? `) ${digits.slice(2,6)}${digits.length > 6 ? `-${digits.slice(6,10)}` : ''}` : ''}`
      : `(${digits.slice(0,2)}) ${digits.slice(2,7)}-${digits.slice(7)}`;
  }));

  document.querySelectorAll('.coming-soon').forEach((item) => {
    item.addEventListener('click', (event) => {
      event.preventDefault();
      notify(`${item.dataset.module}: módulo será desenvolvido nas próximas etapas.`);
    });
  });

  document.querySelector('#logoutButton')?.addEventListener('click', () => ClassManagerApi.logout().catch((error) => notify(error.message)));
  ClassManagerApi.ensureSignedIn().then((user) => {
    document.querySelectorAll('[data-current-user-name]').forEach((node) => { node.textContent = user.nome; });
    const roleLabels={administrador:'Administrador',secretaria:'Secretaria',professor:'Professor'};
    document.querySelectorAll('[data-current-user-role]').forEach((node) => { node.textContent = roleLabels[user.perfil]||user.perfil; });
    if(user.perfil!=='administrador')document.querySelectorAll('.admin-only').forEach((node)=>{node.hidden=true;});
    const visibleByRole={secretaria:['students','classes','enrollments','lessons','invoices','payments'],professor:['students','classes','lessons'],administrador:['teachers','courses','classes','enrollments','lessons','invoices','payments','users']};
    document.querySelectorAll('.side-nav a[href*="module="]').forEach((link)=>{const linkModule=new URL(link.href).searchParams.get('module');if(!(visibleByRole[user.perfil]||[]).includes(linkModule))link.hidden=true;});
    loadStudents();
  }).catch((error) => { message.textContent = error.message; });
});
