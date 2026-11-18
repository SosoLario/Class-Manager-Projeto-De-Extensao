document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(window.location.search);
  let module = params.get('module') || 'teachers';
  const config = {
    teachers: { title:'Professores', description:'Gerencie a equipe docente e os idiomas que leciona.', plural:'professores', status:['ativo','inativo'], fields:[
      ['nome_completo','Nome completo','text',true],['telefone','Telefone','tel'],['email','E-mail','email'],['idiomas','Idiomas que leciona','text',true],['situacao','Situação','select',true,['ativo','inativo']],
    ], columns:[['nome_completo','Professor'],['telefone','Telefone'],['email','E-mail'],['idiomas','Idiomas'],['situacao','Situação']] },
    courses: { title:'Cursos', description:'Organize idiomas, níveis, duração e valores dos cursos.', plural:'cursos', fields:[
      ['nome','Nome do curso','text',true],['idioma','Idioma','text',true],['nivel','Nível','select',true,['básico','intermediário','avançado']],['duracao_meses','Duração (meses)','number',true],['carga_horaria','Carga horária (horas)','number',true],['valor','Valor total do curso','money',true],['ativo','Disponível','select',true,['1','0']],
    ], columns:[['nome','Curso'],['idioma','Idioma'],['nivel','Nível'],['duracao_meses','Duração'],['carga_horaria','Carga horária'],['valor','Valor'],['ativo','Situação']] },
    classes: { title:'Turmas', description:'Vincule curso e professor, defina horários e capacidade.', plural:'turmas', status:['ativa','concluída','cancelada'], fields:[
      ['nome','Nome ou código da turma','text',true],['curso_id','Curso','options: courses',true],['professor_id','Professor','options: teachers',true],['capacidade','Máximo de alunos','number',true],['data_inicio','Data de início','date',true],['data_fim','Data de término','date'],['situacao','Situação','select',true,['ativa','concluída','cancelada']],
    ], schedule:true, columns:[['nome','Turma'],['curso_nome','Curso'],['professor_nome','Professor'],['horarios','Dias e horários'],['capacidade','Vagas'],['situacao','Situação']] },
    enrollments: { title:'Matrículas', description:'Matricule alunos, controle mensalidades acordadas e situações.', plural:'matrículas', status:['ativa','concluída','cancelada','transferida'], fields:[
      ['aluno_id','Aluno','options: students',true],['turma_id','Turma','options: classes',true],['data_matricula','Data da matrícula','date',true],['valor_mensalidade','Valor mensal','money',true],['situacao','Situação','select',true,['ativa','concluída','cancelada','transferida']],['observacoes','Observações','textarea'],
    ], columns:[['aluno_nome','Aluno'],['turma_nome','Turma'],['data_matricula','Data'],['valor_mensalidade','Mensalidade'],['situacao','Situação']] },
    lessons: { title:'Aulas e frequência', description:'Registre aulas, conteúdo ministrado e presença dos alunos.', plural:'aulas', fields:[
      ['turma_id','Turma','options: classes',true],['data_aula','Data da aula','date',true],['hora_inicio','Horário de início','time',true],['hora_fim','Horário de término','time',true],['conteudo','Conteúdo ministrado','textarea'],['professor_substituto_id','Professor substituto','options: substitute_teachers'],
    ], columns:[['turma_nome','Turma'],['data_aula','Data'],['hora_inicio','Início'],['hora_fim','Término'],['substituto_nome','Substituto'],['conteudo','Conteúdo']] },
    invoices: { title:'Mensalidades', description:'Emita mensalidades e acompanhe valores e vencimentos.', plural:'mensalidades', status:['pendente','pago','atrasado','cancelado'], tabs:['invoices','payments'], fields:[
      ['matricula_id','Matrícula','options: enrollments',true],['referencia','Referência','month',true],['valor','Valor','money',true],['desconto','Desconto','money'],['multa','Multa','money'],['juros','Juros','money'],['vencimento','Vencimento','date',true],['situacao','Situação','select',true,['pendente','pago','atrasado','cancelado']],
    ], columns:[['aluno_nome','Aluno'],['turma_nome','Turma'],['referencia','Referência'],['valor','Valor'],['total_pago','Pago'],['vencimento','Vencimento'],['situacao','Situação']] },
    payments: { title:'Pagamentos', description:'Registre pagamentos manualmente e preserve o histórico.', plural:'pagamentos', tabs:['invoices','payments'], fields:[
      ['mensalidade_id','Mensalidade','options: invoices',true],['valor','Valor pago','money',true],['data_pagamento','Data e hora','datetime-local',true],['forma_pagamento','Forma de pagamento','select',true,['dinheiro','pix','cartão','transferência','outro']],['observacoes','Observações','textarea'],
    ], columns:[['aluno_nome','Aluno'],['referencia','Referência'],['valor','Valor pago'],['data_pagamento','Data'],['forma_pagamento','Forma'],['observacoes','Observações']] },
    users: { title:'Usuários e permissões', description:'Crie contas demonstrativas e atribua seus perfis de acesso.', plural:'usuários', fields:[
      ['nome','Nome completo','text',true],['email','E-mail','email',true],['perfil','Perfil de acesso','select',true,['administrador','secretaria','professor','aluno']],['professor_id','Cadastro de professor','options: teachers'],['student_id','Cadastro de aluno','options: students'],['password','Senha inicial (mínimo 10 caracteres)','password'],['ativo','Acesso','select',true,['1','0']],
    ], columns:[['nome','Usuário'],['email','E-mail'],['perfil','Perfil'],['ativo','Acesso']] },
  };

  const current = config[module];
  const message = document.querySelector('#tableMessage');
  const tbody = document.querySelector('#tableBody');
  const modal = new bootstrap.Modal(document.querySelector('#recordModal'));
  const attendanceModal = new bootstrap.Modal(document.querySelector('#attendanceModal'));
  const transferModal = new bootstrap.Modal(document.querySelector('#transferModal'));
  const form = document.querySelector('#recordForm');
  const submitButton = document.querySelector('#saveRecord');
  let editingId = null;
  let activeUser;
  let searchTimer;
  let toastTimer;

  if (!current) { document.querySelector('#moduleTitle').textContent = 'Módulo não encontrado'; return; }
  document.title = `${current.title} | ClassManager`;
  document.querySelector('#moduleTitle').textContent = current.title;
  document.querySelector('#breadcrumbTitle').textContent = current.title;
  document.querySelector('#moduleDescription').textContent = current.description;
  document.querySelector('#recordCountLabel').textContent = current.plural;
  document.querySelector('#todayLabel').textContent = new Intl.DateTimeFormat('pt-BR',{day:'2-digit',month:'long',year:'numeric'}).format(new Date());
  document.querySelector('#searchInput').placeholder = `Buscar ${current.plural}`;
  if (window.lucide) window.lucide.createIcons();

  const dayNames = ['','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado','Domingo'];
  const statusLabels = { ativo:'Ativo', inativo:'Inativo', ativa:'Ativa', concluída:'Concluída', cancelada:'Cancelada', cancelado:'Cancelado', transferida:'Transferida', pendente:'Pendente', pago:'Pago', atrasado:'Atrasado', presente:'Presente', falta:'Falta', atrasado:'Atrasado' };
  const roles = { administrador:'Administrador', secretaria:'Secretaria', professor:'Professor', aluno:'Aluno' };
  const statusSelect = document.querySelector('#statusFilterWrap');
  if (!current.status) statusSelect.hidden = true;
  else document.querySelector('#statusFilter').innerHTML = '<option value="">Todas</option>' + current.status.map((item)=>`<option value="${item}">${statusLabels[item]||item}</option>`).join('');

  if (current.tabs) {
    const tabs = document.querySelector('#moduleTabs');
    tabs.innerHTML = `<a class="module-tab ${module==='invoices'?'active':''}" href="manager.html?module=invoices">Mensalidades</a><a class="module-tab ${module==='payments'?'active':''}" href="manager.html?module=payments">Pagamentos</a>`;
  }

  function escapeHtml(value) { return String(value ?? '').replace(/[&<>"']/g,(ch)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch])); }
  function formatDate(value) { if(!value)return '—'; const parts=String(value).slice(0,10).split('-'); return parts.length===3?`${parts[2]}/${parts[1]}/${parts[0]}`:value; }
  function formatMoney(value) { return Number(value||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'}); }
  function formatPhone(value) { const digits=String(value||'').replace(/\D/g,'');if(digits.length===11)return `(${digits.slice(0,2)}) ${digits.slice(2,7)}-${digits.slice(7)}`;if(digits.length===10)return `(${digits.slice(0,2)}) ${digits.slice(2,6)}-${digits.slice(6)}`;return value||'—'; }
  function formatTime(value) { return String(value||'').slice(0,5)||'—'; }
  function labelFor(field,value) {
    if(field==='situacao') return statusLabels[value]||value;
    if(field==='perfil') return roles[value]||value;
    if(field==='ativo') return Number(value)===1?'Ativo':'Inativo';
    if(['valor','valor_mensalidade','total_pago'].includes(field)) return formatMoney(value);
    if(field==='telefone') return formatPhone(value);
    if(['data_aula','data_matricula','data_inicio','data_fim','vencimento'].includes(field)) return formatDate(value);
    if(['hora_inicio','hora_fim'].includes(field)) return formatTime(value);
    if(field==='horarios') return String(value||'').split(',').filter(Boolean).map((item)=>{const [day,start,end]=item.split('|');return `${dayNames[day]||day} ${start}–${end}`;}).join(', ')||'—';
    return value||'—';
  }
  function notify(text) { const toast=document.querySelector('#toastMessage');toast.textContent=text;toast.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>toast.classList.remove('show'),3200); }
  function renderHead() { document.querySelector('#tableHead').innerHTML=`<tr>${current.columns.map((column)=>`<th>${escapeHtml(column[1].toUpperCase())}</th>`).join('')}<th class="text-end">AÇÕES</th></tr>`; }
  function renderRows(records) {
    document.querySelector('#recordCount').textContent=records.length;
    if(!records.length){tbody.innerHTML=`<tr class="empty-row"><td colspan="${current.columns.length+1}">Nenhum registro encontrado.</td></tr>`;return;}
    tbody.innerHTML=records.map((record)=>`<tr>${current.columns.map(([field])=>{
      const value=labelFor(field,record[field]);
      if(field==='situacao'||field==='ativo') {const state=field==='ativo'?(Number(record.ativo)===1?'ativo':'inativo'):record.situacao;return `<td><span class="status-pill status-${escapeHtml(state)}">${escapeHtml(value)}</span></td>`;}
      if(field==='nome_completo'||field==='nome')return `<td><div class="student-cell"><span class="student-avatar">${escapeHtml(String(record[field]||'?').trim().split(/\s+/).slice(0,2).map((part)=>part[0]).join('').toUpperCase())}</span><span class="student-name"><strong>${escapeHtml(value)}</strong></span></div></td>`;
      return `<td>${escapeHtml(value)}</td>`;
    }).join('')}<td><div class="row-actions">${module==='lessons'?`<button type="button" class="row-action attendance" data-id="${Number(record.id)}" title="Frequência" aria-label="Registrar frequência"><i data-lucide="list-checks"></i></button>`:''}${module==='enrollments'&&record.situacao==='ativa'?`<button type="button" class="row-action transfer" data-id="${Number(record.id)}" title="Transferir" aria-label="Transferir matrícula"><i data-lucide="arrow-right-left"></i></button>`:''}${module!=='payments'?`<button type="button" class="row-action edit" data-id="${Number(record.id)}" title="Editar" aria-label="Editar"><i data-lucide="pencil"></i></button>`:''}${!['payments','lessons'].includes(module)?`<button type="button" class="row-action archive" data-id="${Number(record.id)}" title="Arquivar" aria-label="Arquivar"><i data-lucide="archive"></i></button>`:''}</div></td></tr>`).join('');
    if(window.lucide)window.lucide.createIcons();
  }
  async function loadRecords() {
    message.textContent='Carregando registros…';
    const query=new URLSearchParams();const search=document.querySelector('#searchInput').value.trim();const status=document.querySelector('#statusFilter').value;
    if(search)query.set('search',search);if(status)query.set('status',status);
    try { const result=await ClassManagerApi.request(`${module}?${query.toString()}`);message.textContent='';renderRows(result.data||[]); }
    catch(error){tbody.innerHTML='';document.querySelector('#recordCount').textContent='—';message.textContent=error.message;}
  }
  async function optionsFor(moduleName) { const result=await ClassManagerApi.request(`options/${moduleName}`);return result.data||[]; }
  function fieldMarkup([name,label,type,required,choices]) {
    const span=type==='textarea'||name==='nome_completo'&&module==='teachers'?' span-2':'';
    let control='';
    if(type==='select') {
      let options=choices.map((choice)=>{const label=roles[choice]||statusLabels[choice]||(choice==='1'?'Ativo':choice==='0'?'Inativo':choice);return `<option value="${escapeHtml(choice)}">${escapeHtml(label)}</option>`;}).join('');
      control=`<select name="${name}" ${required?'required':''}>${options}</select>`;
    } else if(type.startsWith('options:')) control=`<select name="${name}" data-options="${type.slice(8)}" ${required?'required':''}><option value="">Selecione</option></select>`;
    else if(type==='textarea') control=`<textarea name="${name}" rows="3" maxlength="1000" ${required?'required':''}></textarea>`;
    else if(type==='money') control=`<input name="${name}" type="text" inputmode="decimal" data-mask="money" ${required?'required':''} placeholder="R$ 0,00">`;
    else if(type==='number') control=`<input name="${name}" type="number" min="${name==='valor'?'0':'1'}" step="${name==='valor'?'0.01':'1'}" ${required?'required':''}>`;
    else control=`<input name="${name}" type="${type}" ${required?'required':''} ${type==='password'?'minlength="10" autocomplete="new-password"':''} ${type==='password'?'placeholder="Deixe vazio para manter a senha"':''}>`;
    return `<label class="form-field${span}"><span>${escapeHtml(label)}${required?' <b>*</b>':''}</span>${control}<small class="field-error"></small></label>`;
  }
  async function prepareForm(record={}) {
    document.querySelector('#recordFields').innerHTML=current.fields.filter(field=>!(module==='lessons'&&field[0]==='professor_substituto_id'&&activeUser?.perfil!=='administrador')).map(fieldMarkup).join('')+(current.schedule?`<div class="schedule-box span-2"><div class="schedule-title">Selecione os dias e defina os horários</div><div class="schedule-rows">${dayNames.slice(1).map((day,index)=>`<div class="schedule-row"><label><input type="checkbox" name="schedule_day" value="${index+1}"><span>${day}</span></label><input name="schedule_start_${index+1}" type="time" aria-label="Início ${day}" disabled><input name="schedule_end_${index+1}" type="time" aria-label="Término ${day}" disabled></div>`).join('')}</div></div>`:'');
    const optionFields=[...document.querySelectorAll('#recordFields [data-options]')];
    await Promise.all(optionFields.map(async(select)=>{try{const options=await optionsFor(select.dataset.options.trim());select.innerHTML='<option value="">Selecione</option>'+options.map((option)=>`<option value="${Number(option.id)}">${escapeHtml(option.label)}</option>`).join('');}catch(error){select.innerHTML=`<option value="">${escapeHtml(error.message)}</option>`;}}));
    const now = new Date();
    const localDate = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
    const localDateTime = `${localDate}T${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`;
    for(const field of current.fields){const control=form.elements.namedItem(field[0]);if(!control)continue;let value=record[field[0]]??'';if(field[0]==='ativo')value=String(Number(value??1));if(field[0]==='situacao'&&!record.id)value=value||field[4]?.[0]||'';if(field[0]==='data_pagamento')value=value?String(value).replace(' ','T').slice(0,16):localDateTime;if(field[0]==='referencia'&&!value)value=localDate.slice(0,7);if(['data_inicio','data_matricula','data_aula','vencimento'].includes(field[0])&&!value)value=localDate;if(field[0]==='data_fim'&&value)value=String(value).slice(0,10);if(field[0]==='valor'||field[0]==='valor_mensalidade'||field[0]==='desconto'||field[0]==='multa'||field[0]==='juros')value=value===''?'':formatMoney(value);if(field[0]==='telefone')value=formatPhone(String(value));if(['data_inicio','data_fim','data_matricula','data_aula','vencimento'].includes(field[0])&&value)value=String(value).slice(0,10);control.value=value;}
    if(module==='users'){const syncLinkedProfile=()=>{document.querySelector('[name="professor_id"]')?.closest('.form-field').classList.toggle('d-none',form.elements.perfil.value!=='professor');document.querySelector('[name="student_id"]')?.closest('.form-field').classList.toggle('d-none',form.elements.perfil.value!=='aluno');};form.elements.perfil.addEventListener('change',syncLinkedProfile);syncLinkedProfile();}
    if(current.schedule&&record.horarios){for(const entry of String(record.horarios).split(',')){const [day,start,end]=entry.split('|');const check=document.querySelector(`input[name="schedule_day"][value="${day}"]`);if(check){check.checked=true;const startInput=document.querySelector(`[name="schedule_start_${day}"]`);const endInput=document.querySelector(`[name="schedule_end_${day}"]`);startInput.disabled=false;endInput.disabled=false;startInput.required=true;endInput.required=true;startInput.value=start;endInput.value=end;}}}
    clearFormError();
    if(window.lucide)window.lucide.createIcons();
  }
  function clearFormError(){document.querySelectorAll('#recordFields .is-invalid').forEach((element)=>element.classList.remove('is-invalid'));document.querySelectorAll('#recordFields .field-error').forEach((element)=>element.textContent='');const old=form.querySelector('.student-form-error');if(old)old.remove();}
  function showFormError(text){let error=form.querySelector('.student-form-error');if(!error){error=document.createElement('p');error.className='student-form-error';form.querySelector('.modal-body').prepend(error);}error.textContent=text;}
  function parseMoney(text){return String(text||'').replace(/[^0-9,.-]/g,'').replace(/\./g,'').replace(',','.');}
  function maskMoney(input){const digits=input.value.replace(/\D/g,'');if(!digits){input.value='';return;}const value=(Number(digits)/100).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});input.value=`R$ ${value}`;}
  function formatPhone(text){let digits=text.replace(/\D/g,'').slice(0,11);if(!digits)return '';if(digits.length<=2)return `(${digits}`;if(digits.length<=10)return `(${digits.slice(0,2)}) ${digits.slice(2,6)}${digits.length>6?'-'+digits.slice(6,10):''}`;return `(${digits.slice(0,2)}) ${digits.slice(2,7)}-${digits.slice(7)}`;}

  document.querySelector('#newRecord').addEventListener('click',async()=>{editingId=null;form.reset();document.querySelector('#recordModalTitle').textContent='Novo registro';await prepareForm();modal.show();});
  document.querySelector('#searchInput').addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(loadRecords,250);});
  document.querySelector('#statusFilter').addEventListener('change',loadRecords);
  document.querySelector('#tableBody').addEventListener('click',async(event)=>{
    const button=event.target.closest('button');if(!button)return;const id=Number(button.dataset.id);
    if(button.classList.contains('transfer')){const transferForm=document.querySelector('#transferForm');transferForm.dataset.enrollmentId=id;transferForm.reset();const now=new Date();transferForm.elements.data_matricula.value=`${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;const select=transferForm.elements.turma_id;select.innerHTML='<option value="">Carregando turmas…</option>';try{const options=await optionsFor('classes');select.innerHTML='<option value="">Selecione uma turma</option>'+options.map((option)=>`<option value="${Number(option.id)}">${escapeHtml(option.label)}</option>`).join('');transferModal.show();}catch(error){notify(error.message);}return;}
    if(button.classList.contains('attendance')){try{const result=await ClassManagerApi.request(`attendance?lesson_id=${id}`);document.querySelector('#attendanceTitle').textContent='Frequência da aula';document.querySelector('#attendanceRows').innerHTML=result.data.length?result.data.map((student)=>`<div class="attendance-row"><strong>${escapeHtml(student.nome_completo)}</strong><select name="attendance_${Number(student.student_id)}"><option value="presente" ${student.situacao==='presente'?'selected':''}>Presente</option><option value="falta" ${student.situacao==='falta'?'selected':''}>Falta</option><option value="atrasado" ${student.situacao==='atrasado'?'selected':''}>Atrasado</option></select></div>`).join(''):'<p class="text-secondary">Não há alunos matriculados nesta turma.</p>';document.querySelector('#attendanceForm').dataset.lessonId=id;attendanceModal.show();}catch(error){notify(error.message);}return;}
    if(button.classList.contains('edit')){try{const result=await ClassManagerApi.request(`${module}/${id}`);editingId=id;document.querySelector('#recordModalTitle').textContent=`Editar ${current.title.toLowerCase()}`;await prepareForm(result.data);modal.show();}catch(error){notify(error.message);}return;}
    if(button.classList.contains('archive')){const row=button.closest('tr');const label=row?.querySelector('.student-name strong')?.textContent||row?.cells[0]?.textContent.trim()||'este registro';if(!confirm(`Arquivar ${label}? O histórico será preservado.`))return;try{await ClassManagerApi.request(`${module}/${id}`,{method:'DELETE',body:{}});notify('Registro arquivado.');await loadRecords();}catch(error){notify(error.message);}}
  });
  form.addEventListener('input',(event)=>{if(event.target.dataset.mask==='money')maskMoney(event.target);if(event.target.type==='tel')event.target.value=formatPhone(event.target.value);});
  form.addEventListener('change',(event)=>{if(event.target.name==='schedule_day'){const day=event.target.value;const start=form.elements.namedItem(`schedule_start_${day}`);const end=form.elements.namedItem(`schedule_end_${day}`);start.disabled=!event.target.checked;end.disabled=!event.target.checked;start.required=event.target.checked;end.required=event.target.checked;}});
  form.addEventListener('submit',async(event)=>{event.preventDefault();clearFormError();if(!form.reportValidity())return;const payload=Object.fromEntries(new FormData(form).entries());delete payload.schedule_day;for(const name of ['valor','valor_mensalidade','desconto','multa','juros'])if(payload[name]!==undefined)payload[name]=parseMoney(payload[name]);if(payload.ativo!==undefined)payload.ativo=Number(payload.ativo);if(payload.telefone)payload.telefone=payload.telefone.replace(/\D/g,'');if(current.schedule){const days=[...document.querySelectorAll('input[name="schedule_day"]:checked')].map((check)=>Number(check.value));payload.horarios=days.map((day)=>({dia_semana:day,hora_inicio:form.elements.namedItem(`schedule_start_${day}`).value,hora_fim:form.elements.namedItem(`schedule_end_${day}`).value}));}
    if(current.schedule){payload.horarios=[...document.querySelectorAll('input[name="schedule_day"]:checked')].map((check)=>({dia_semana:Number(check.value),hora_inicio:form.elements.namedItem(`schedule_start_${check.value}`).value,hora_fim:form.elements.namedItem(`schedule_end_${check.value}`).value}));}
    submitButton.disabled=true;submitButton.textContent='Salvando…';try{await ClassManagerApi.request(`${module}${editingId?`/${editingId}`:''}`,{method:editingId?'PUT':'POST',body:payload});modal.hide();notify('Registro salvo com sucesso.');await loadRecords();}catch(error){showFormError(error.message);}finally{submitButton.disabled=false;submitButton.innerHTML='<i data-lucide="check"></i> Salvar';if(window.lucide)window.lucide.createIcons();}});
  document.querySelector('#attendanceForm').addEventListener('submit',async(event)=>{event.preventDefault();const attendanceForm=event.currentTarget;const lessonId=Number(attendanceForm.dataset.lessonId);const records=[...attendanceForm.querySelectorAll('select[name^="attendance_"]')].map((select)=>({aluno_id:Number(select.name.replace('attendance_','')),situacao:select.value}));try{await ClassManagerApi.request(`attendance/${lessonId}`,{method:'POST',body:{records}});attendanceModal.hide();notify('Frequência salva.');}catch(error){notify(error.message);}});
  document.querySelector('#transferForm').addEventListener('submit',async(event)=>{event.preventDefault();const transferForm=event.currentTarget;if(!transferForm.reportValidity())return;const button=transferForm.querySelector('[type="submit"]');button.disabled=true;try{await ClassManagerApi.request(`enrollments/${Number(transferForm.dataset.enrollmentId)}`,{method:'POST',body:{action:'transfer',turma_id:Number(transferForm.elements.turma_id.value),data_matricula:transferForm.elements.data_matricula.value}});transferModal.hide();notify('Matrícula transferida. Histórico preservado.');await loadRecords();}catch(error){notify(error.message);}finally{button.disabled=false;}});
  document.querySelector('#logoutButton').addEventListener('click',()=>ClassManagerApi.logout().catch((error)=>notify(error.message)));
  document.querySelector('#sidebarScrim').addEventListener('click',()=>{document.querySelector('#sidebar').classList.remove('open');document.querySelector('#sidebarScrim').classList.remove('visible');});
  document.querySelector('#menuToggle').addEventListener('click',()=>{document.querySelector('#sidebar').classList.toggle('open');document.querySelector('#sidebarScrim').classList.toggle('visible');});

  renderHead();
  try{activeUser=await ClassManagerApi.ensureSignedIn();if(activeUser.perfil==='aluno'){window.location.replace('area-aluno.html');return;}document.querySelectorAll('[data-current-user-name]').forEach((node)=>node.textContent=activeUser.nome);document.querySelectorAll('[data-current-user-role]').forEach((node)=>node.textContent=roles[activeUser.perfil]||activeUser.perfil);if(activeUser.perfil!=='administrador')document.querySelectorAll('.admin-only').forEach((node)=>node.hidden=true);const visibleByRole={secretaria:['students','classes','enrollments','lessons','invoices','payments'],professor:['students','classes','lessons'],administrador:['teachers','courses','classes','enrollments','lessons','invoices','payments','users']};document.querySelectorAll('.side-nav a[href*="module="]').forEach((link)=>{const linkModule=new URL(link.href).searchParams.get('module');if(!(visibleByRole[activeUser.perfil]||[]).includes(linkModule))link.hidden=true;});const canCreate=(module==='lessons'&&activeUser.perfil==='professor')||(['administrador'].includes(activeUser.perfil))||(['students','enrollments','payments'].includes(module)&&activeUser.perfil==='secretaria');if(!canCreate)document.querySelector('#newRecord').hidden=true;await loadRecords();}catch(error){message.textContent=error.message;}
});
