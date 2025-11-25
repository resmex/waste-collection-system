async function loadAdmin() {
  const res = await fetch('../api/municipal-get-dashboard-data.php');
  const j = await res.json();
  // stats
  document.getElementById('statTotalTrips').textContent = j.stats.totalTrips;
  document.getElementById('statNew').textContent = j.stats.newRequests;
  document.getElementById('statPendingVeh').textContent = j.stats.pendingVeh;

  // requests list
  const reqEl = document.getElementById('requestsList');
  reqEl.innerHTML = (j.requests||[]).map(r=>`
    <div class="pickup-item">
      <div>
        <div><strong>#${r.id}</strong> • ${r.address||'—'}</div>
        <div>${r.status} • ${new Date(r.created_at).toLocaleString()}</div>
      </div>
      <button class="view-btn" data-id="${r.id}">Assign</button>
    </div>
  `).join('');

  // vehicles approvals
  const vehEl = document.getElementById('vehicleApprovals');
  vehEl.innerHTML = (j.vehicles||[]).map(v=>`
    <div class="truck-owner-item">
      <div><strong>${v.plate_no||'Plate'}</strong> • ${v.capacity_kg||'—'} kg</div>
      <div class="status-badge ${v.status==='pending'?'status-pending':(v.status==='approved'?'status-completed':'status-progress')}">
        ${v.status}
      </div>
      ${v.status==='pending'? `<button class="view-btn" data-veh="${v.id}">Review</button>`:''}
    </div>
  `).join('');

  // feedback
  const fbEl = document.getElementById('feedbackList');
  fbEl.innerHTML = (j.feedback||[]).map(f=>`
    <div class="feedback-item">
      <div><strong>${f.resident_name||'Resident'}</strong> • ★${f.rating}</div>
      <div class="feedback-text">${(f.comments||'').replace(/</g,'&lt;')}</div>
    </div>
  `).join('');
}

document.addEventListener('DOMContentLoaded', loadAdmin);

