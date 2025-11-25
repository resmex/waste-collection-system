let map, userMarker, driverMarker, watchId, myPos = null;
let currentDriverId = null;   // set after assignment fetch
let currentRequestId = null;  // set after request
const pollMs = 4000;

function initMap() {
  map = new google.maps.Map(document.getElementById('map'), {
    center: {lat: -6.8123, lng: 39.2796}, zoom: 14, mapId: "DEMO_MAP_ID"
  });

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      myPos = { lat: pos.coords.latitude, lng: pos.coords.longitude };
      map.setCenter(myPos);
      userMarker = new google.maps.Marker({ position: myPos, map, label: "You" });
      // push my location to server (role=resident)
      sendMyLocation(myPos);
      // keep updating
      watchId = navigator.geolocation.watchPosition(p => {
        myPos = { lat: p.coords.latitude, lng: p.coords.longitude };
        userMarker.setPosition(myPos);
        sendMyLocation(myPos);
      });
    });
  }
}
window.initMap = initMap;

function sendMyLocation({lat,lng}) {
  fetch('../api/update-location.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ lat, lng, role:'resident' })
  });
}

window.requestPickup = async function requestPickup() {
  if (!myPos) { alert('Allow location first'); return; }
  const form = new URLSearchParams({
    lat: myPos.lat, lng: myPos.lng, address: 'Resident current location'
  });
  const res = await fetch('../api/request-pickup.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:form});
  const data = await res.json();
  if (data.ok) {
    currentRequestId = data.request_id;
    alert('Pickup requested. Admin will assign a driver.');
  } else alert(data.error || 'Failed');
}

let trackTimer=null;
window.trackDriver = async function trackDriver() {
  // Normally: fetch assignment by request to get driver_id; here poll a saved driverId if server returns it elsewhere
  if (!currentDriverId) {
    // try to resolve from the server by latest assignment for my request
    if (!currentRequestId) { alert('No assignment yet.'); return; }
    const q = await fetch(`../api/get-requests.php?status=in_progress`);
    const j = await q.json();
    const mine = (j.data||[]).find(r=>r.id===currentRequestId);
    if (mine && mine.driver_id) currentDriverId = mine.driver_id;
    if (!currentDriverId) { alert('Driver not assigned yet.'); return; }
  }

  if (trackTimer) clearInterval(trackTimer);
  trackTimer = setInterval(async () => {
    const r = await fetch(`../api/get-live-location.php?user_id=${currentDriverId}&role=driver`);
    const j = await r.json();
    if (j.location) {
      const p = { lat: parseFloat(j.location.lat), lng: parseFloat(j.location.lng) };
      if (!driverMarker) {
        driverMarker = new google.maps.Marker({ position:p, map, label:"Truck" });
      } else {
        driverMarker.setPosition(p);
      }
    }
  }, pollMs);
};

// Star rating UI (already in your HTML)
document.addEventListener('click', e => {
  if (!e.target.classList.contains('star')) return;
  const rating = Number(e.target.dataset.rating);
  document.getElementById('selectedRating').value = rating;
  document.querySelectorAll('#starRating .star').forEach(s=>{
    s.textContent = (Number(s.dataset.rating) <= rating) ? '★' : '☆';
  });
});

window.submitFeedback = async function submitFeedback() {
  const rating = Number(document.getElementById('selectedRating').value || 0);
  const comments = document.getElementById('feedbackText').value.trim();
  if (!currentRequestId) { alert('No request yet'); return; }
  const form = new URLSearchParams({
    request_id: currentRequestId,
    driver_id: currentDriverId || 0, rating, comments
  });
  const res = await fetch('../api/submit-feedback.php',{
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:form
  });
  const data = await res.json();
  if (data.ok) { alert('Thanks for your feedback!'); }
  else alert(data.error || 'Failed');
};

// Boot
document.addEventListener('DOMContentLoaded', ()=>{
  if (document.getElementById('map')) initMap();
});
