document.addEventListener('DOMContentLoaded', async () => {
  const today = new Date();
  const roleNames = { administrador: 'Administrador', secretaria: 'Secretaria', professor: 'Professor' };
  const formatMoney = (value) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[ch]));
  const dateLabel = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' }).format(today);
  document.querySelector('#todayLabel').textContent = dateLabel;
  document.querySelector('.page-heading .eyebrow').textContent = `${new Intl.DateTimeFormat('pt-BR', { weekday: 'long' }).format(today).toUpperCase()} • VISÃO GERAL`;
  document.querySelector('#currentMonth').textContent = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(today);
  if (window.lucide) window.lucide.createIcons();

  const sidebar = document.querySelector('#sidebar');
  const scrim = document.querySelector('#sidebarScrim');
  const menuToggle = document.querySelector('#menuToggle');
  const closeMenu = () => { sidebar.classList.remove('open'); scrim.classList.remove('visible'); menuToggle.setAttribute('aria-expanded','false'); };
  menuToggle.addEventListener('click', () => { const open=sidebar.classList.toggle('open');scrim.classList.toggle('visible',open);menuToggle.setAttribute('aria-expanded',String(open)); });
  scrim.addEventListener('click', closeMenu);
  document.querySelector('#logoutButton').addEventListener('click', () => ClassManagerApi.logout());

  let user;
  try { user = await ClassManagerApi.ensureSignedIn(); }
  catch (error) { document.querySelector('.page-heading').insertAdjacentHTML('afterend',`<div class="alert alert-danger">${escapeHtml(error.message)}</div>`); return; }
  if (user.perfil === 'aluno') { window.location.replace('pages/area-aluno.html'); return; }
  const greeting = document.querySelector('.page-heading h1');
  if (greeting?.firstChild) greeting.firstChild.textContent = `Olá, ${user.nome.split(/\s+/)[0]} `;
  document.querySelectorAll('[data-current-user-name]').forEach((node) => { node.textContent=user.nome; });
  document.querySelectorAll('[data-current-user-role]').forEach((node) => { node.textContent=roleNames[user.perfil]||user.perfil; });
  if (user.perfil !== 'administrador') document.querySelectorAll('.admin-only').forEach((node)=>{node.hidden=true;});
  if (user.perfil === 'professor') {
    document.querySelector('.revenue-panel').hidden=true;
    document.querySelector('.finance-panel').hidden=true;
    document.querySelector('.activity-panel').hidden=true;
    document.querySelectorAll('a[href*="module=teachers"],a[href*="module=courses"],a[href*="module=enrollments"],a[href*="module=invoices"]').forEach((node)=>{node.hidden=true;});
  }
  if (user.perfil === 'secretaria') document.querySelectorAll('a[href*="module=teachers"],a[href*="module=courses"]').forEach((node)=>{node.hidden=true;});

  const canvas = document.querySelector('#revenueChart');
  const chart = window.Chart && canvas ? new Chart(canvas, {
    type:'line', data:{labels:[],datasets:[{data:[],borderColor:'#4169dc',backgroundColor:(context)=>{const area=context.chart.chartArea;if(!area)return 'rgba(65,105,220,.1)';const gradient=context.chart.ctx.createLinearGradient(0,area.top,0,area.bottom);gradient.addColorStop(0,'rgba(65,105,220,.18)');gradient.addColorStop(1,'rgba(65,105,220,0)');return gradient;},borderWidth:2,pointRadius:0,pointHoverRadius:4,pointBackgroundColor:'#4169dc',fill:true,tension:.38}]},
    options:{responsive:true,maintainAspectRatio:false,interaction:{intersect:false,mode:'index'},plugins:{legend:{display:false},tooltip:{callbacks:{label:(context)=>` ${formatMoney(context.parsed.y)}`}}},scales:{x:{grid:{display:false},border:{display:false},ticks:{color:'#9aa3b0',font:{size:9}}},y:{beginAtZero:true,border:{display:false,dash:[3,4]},grid:{color:'#f0f2f6'},ticks:{color:'#9aa3b0',font:{size:9},count:4,callback:(value)=>`R$ ${(value/1000).toLocaleString('pt-BR')}k`}}}},
  }) : null;

  try {
    const { data } = await ClassManagerApi.request('dashboard');
    const stat = data.statistics;
    document.querySelector('#statStudents').textContent=stat.students;
    document.querySelector('#statTeachers').textContent=stat.teachers;
    document.querySelector('#statClasses').textContent=stat.classes;
    document.querySelector('#statEnrollments').textContent=stat.enrollments;
    document.querySelector('#financeReceived').textContent=formatMoney(stat.received);
    document.querySelector('#financePending').textContent=formatMoney(stat.pending);
    document.querySelector('#financeOverdue').textContent=formatMoney(stat.overdue);

    const englishMonths={Jan:'Jan',Feb:'Fev',Mar:'Mar',Apr:'Abr',May:'Mai',Jun:'Jun',Jul:'Jul',Aug:'Ago',Sep:'Set',Oct:'Out',Nov:'Nov',Dec:'Dez'};
    const labels=(data.revenue.labels||[]).map((month)=>englishMonths[month]||month);
    const values=data.revenue.values||[];
    if(chart){chart.data.labels=labels;chart.data.datasets[0].data=values;chart.update();}
    const showSemester=document.querySelector('#chartPeriod').value==='semester';
    const currentValues=showSemester?values.slice(-6):values;
    document.querySelector('#revenueTotal').textContent=formatMoney(currentValues.reduce((sum,value)=>sum+Number(value),0));

    const classes=document.querySelector('#upcomingLessons');
    classes.innerHTML=data.upcoming_lessons.length?data.upcoming_lessons.map((lesson)=>`<div class="class-row"><div class="class-time">${escapeHtml(String(lesson.data_aula).slice(5,10).replace('-','/'))}<small>${escapeHtml(String(lesson.hora_inicio).slice(0,5))}–${escapeHtml(String(lesson.hora_fim).slice(0,5))}</small></div><div class="class-color color-blue"></div><div class="class-info"><strong>${escapeHtml(lesson.idioma)} • ${escapeHtml(lesson.nivel)}</strong><span>${escapeHtml(lesson.turma_nome)}</span><small>${escapeHtml(lesson.professor_nome)}</small></div><span class="class-count"><i data-lucide="users"></i> ${Number(lesson.alunos)}</span></div>`).join(''):'<p class="text-secondary small py-3">Nenhuma aula futura cadastrada.</p>';

    const activity=document.querySelector('#recentActivity');
    activity.innerHTML=data.recent_activity.length?data.recent_activity.map((item)=>`<div class="activity-row"><span class="activity-icon ${item.tipo==='pagamento'?'green':'blue'}"><i data-lucide="${item.tipo==='pagamento'?'badge-dollar-sign':'user-plus'}"></i></span><div><strong>${escapeHtml(item.descricao)}</strong><p>${new Intl.DateTimeFormat('pt-BR',{dateStyle:'short',timeStyle:'short'}).format(new Date(item.momento))}</p></div></div>`).join(''):'<p class="text-secondary small py-3">Ainda não há atividades registradas.</p>';
    if(window.lucide)window.lucide.createIcons();

    document.querySelector('#chartPeriod').addEventListener('change',(event)=>{const semester=event.target.value==='semester';if(chart){chart.data.labels=semester?labels.slice(-6):labels;chart.data.datasets[0].data=semester?values.slice(-6):values;chart.update();}document.querySelector('#revenueTotal').textContent=formatMoney((semester?values.slice(-6):values).reduce((sum,value)=>sum+Number(value),0));});
  } catch(error) {
    document.querySelector('.page-heading').insertAdjacentHTML('afterend',`<div class="alert alert-warning" role="alert">Não foi possível carregar o dashboard do banco: ${escapeHtml(error.message)}</div>`);
  }
});
