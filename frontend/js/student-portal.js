document.addEventListener('DOMContentLoaded', async () => {
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[character]));
  const money = (value) => Number(value || 0).toLocaleString('pt-BR', { style:'currency', currency:'BRL' });
  const date = (value) => { if (!value) return '—'; const parts=String(value).slice(0,10).split('-'); return parts.length===3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : '—'; };
  const statusLabels = { ativo:'Ativo', ativa:'Ativa', concluída:'Concluída', cancelada:'Cancelada', transferida:'Transferida', pendente:'Pendente', pago:'Pago', atrasado:'Atrasado', cancelado:'Cancelado', presente:'Presente', falta:'Falta', atrasado:'Atrasado' };
  document.querySelector('#todayLabel').textContent = new Intl.DateTimeFormat('pt-BR',{day:'2-digit',month:'long',year:'numeric'}).format(new Date());
  if (window.lucide) window.lucide.createIcons();
  const sidebar=document.querySelector('#sidebar');
  const scrim=document.querySelector('#sidebarScrim');
  document.querySelector('#menuToggle').addEventListener('click',()=>{sidebar.classList.toggle('open');scrim.classList.toggle('visible');});
  scrim.addEventListener('click',()=>{sidebar.classList.remove('open');scrim.classList.remove('visible');});

  try {
    const user = await ClassManagerApi.ensureSignedIn();
    if (user.perfil !== 'aluno') { window.location.replace('../index.html'); return; }
    document.querySelector('#logoutButton').addEventListener('click', () => ClassManagerApi.logout());
    const { data } = await ClassManagerApi.request('student-portal');
    const firstName = data.student.nome_completo.split(/\s+/)[0];
    document.querySelector('#studentName').textContent = data.student.nome_completo;
    document.querySelector('#greetingName').textContent = firstName;
    const active = data.enrollments.filter((item) => item.situacao === 'ativa');
    document.querySelector('#activeEnrollments').textContent = active.length;
    document.querySelector('#lessonCount').textContent = data.lessons.length;
    document.querySelector('#pendingCount').textContent = data.invoices.filter((item) => ['pendente','atrasado'].includes(item.situacao)).length;

    const days = ['','Segunda','Terça','Quarta','Quinta','Sexta','Sábado','Domingo'];
    document.querySelector('#enrollmentRows').innerHTML = data.enrollments.length ? data.enrollments.map((item) => {
      const schedule = String(item.horarios || '').split(',').filter(Boolean).map((part) => { const [day,start,end]=part.split('|'); return `${days[Number(day)] || 'Dia'} ${start}–${end}`; }).join('<br>') || 'Horário não definido';
      return `<tr><td><strong>${escapeHtml(item.idioma)} · ${escapeHtml(item.curso_nome)}</strong><small class="d-block text-secondary">${escapeHtml(item.nivel)}</small></td><td>${escapeHtml(item.turma_nome)}</td><td>${schedule}</td><td>${escapeHtml(statusLabels[item.situacao] || item.situacao)}</td></tr>`;
    }).join('') : '<tr><td colspan="4">Você ainda não possui matrículas.</td></tr>';

    document.querySelector('#lessonRows').innerHTML = data.lessons.length ? data.lessons.map((item) => `<tr><td>${date(item.data_aula)}<small class="d-block text-secondary">${escapeHtml(String(item.hora_inicio).slice(0,5))}–${escapeHtml(String(item.hora_fim).slice(0,5))}</small></td><td>${escapeHtml(item.turma_nome)}<small class="d-block text-secondary">${escapeHtml(item.idioma)} · ${escapeHtml(item.nivel)}</small></td><td>${escapeHtml(item.conteudo || '—')}</td><td>${escapeHtml(item.frequencia ? statusLabels[item.frequencia] : 'Não lançada')}</td></tr>`).join('') : '<tr><td colspan="4">Nenhuma aula registrada no período.</td></tr>';

    document.querySelector('#invoiceRows').innerHTML = data.invoices.length ? data.invoices.map((item) => `<tr><td>${escapeHtml(item.referencia)}</td><td>${date(item.vencimento)}</td><td>${money(item.valor)}</td><td>${money(item.total_pago)}</td><td>${money(item.saldo)}</td><td><span class="status-pill status-${escapeHtml(item.situacao)}">${escapeHtml(statusLabels[item.situacao] || item.situacao)}</span></td></tr>`).join('') : '<tr><td colspan="6">Nenhuma mensalidade cadastrada.</td></tr>';
    if (window.lucide) window.lucide.createIcons();
  } catch (error) {
    const box=document.querySelector('#portalError');box.textContent=error.message;box.classList.remove('d-none');
  }
});
