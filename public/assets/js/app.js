const REFRESH_DATA_INTERVAL = 60000
const ITEMS_PER_PAGE = 10

// Config Durasi Slide (Kanan)
const SLIDE_DURATION_LOCATOR = 60000 // 15 detik tampil WIP Locator
const SLIDE_DURATION_TRANSIT = 60000 // 15 detik tampil In-Transit

// Global State
let globalLocatorData = []
let globalTransitData = []
let lastReceivedSJ = null // Untuk track notifikasi baru

// Carousel State
let currentRightPanelMode = 'LOCATOR' // 'LOCATOR' or 'TRANSIT'
let currentLocatorPage = 0
let currentTransitPage = 0
let carouselTimer = null

const fmtNum = new Intl.NumberFormat('en-US')

// --- Clock ---
const updateTime = () => {
  const now = new Date()
  const clockEl = document.getElementById('clock')
  const dateEl = document.getElementById('date-display')
  if (clockEl)
    clockEl.innerHTML = `${String(now.getHours()).padStart(
      2,
      '0'
    )}<span class="animate-pulse text-slate-500">:</span>${String(now.getMinutes()).padStart(
      2,
      '0'
    )}`
  if (dateEl) {
    const options = { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' }
    dateEl.innerText = now.toLocaleDateString('en-GB', options).toUpperCase()
  }
}

// --- Data Fetching ---
async function fetchData() {
  const loading = document.getElementById('loading-indicator')
  if (loading) loading.classList.remove('hidden')

  try {
    const response = await fetch('api.php')
    const result = await response.json()
    if (result.status === 'success') {
      renderDeliveryScoreboard(result.data.delivery_progress)

      // Update Global Data
      globalLocatorData = result.data.locator_mapping
      globalTransitData = result.data.in_transit_sj

      // Cek Notifikasi Baru
      checkRecentDelivery(result.data.recent_delivery)
    }
  } catch (error) {
    console.error('Sync Error', error)
  } finally {
    if (loading) loading.classList.add('hidden')
  }
}

// --- Logic Toast Notification ---
function checkRecentDelivery(recentData) {
  if (!recentData) return

  // Jika data SJ berbeda dari yang terakhir disimpan, munculkan toast
  if (lastReceivedSJ !== recentData.no_sj) {
    lastReceivedSJ = recentData.no_sj
    showToast(recentData)
  }
}

function showToast(data) {
  const toast = document.getElementById('toast-notification')
  const img = document.getElementById('toast-img')
  const title = document.getElementById('toast-sj')
  const desc = document.getElementById('toast-desc')
  const time = document.getElementById('toast-time')

  if (toast) {
    // Set Content
    title.innerText = data.no_sj
    desc.innerText = `${data.model_sample} • Total Qty: ${fmtNum.format(data.total_qty)}`
    time.innerText = `Received at: ${data.received_at}`

    // Set Image
    if (data.received_image_url) {
      img.src = data.received_image_url

      // FIX: Hapus inline style 'display: none' yang mungkin diset oleh onerror sebelumnya
      img.style.display = ''

      img.classList.remove('hidden')
    } else {
      img.classList.add('hidden')
    }

    // Show Animation
    toast.classList.remove('toast-hidden')
    toast.classList.add('toast-visible')

    // Hide after 10 seconds
    setTimeout(() => {
      toast.classList.remove('toast-visible')
      toast.classList.add('toast-hidden')
    }, 10000)
  }
}

// --- Render Left Panel (Delivery + Received) ---
function renderDeliveryScoreboard(data) {
  const container = document.getElementById('delivery-container')
  if (!container) return

  container.innerHTML = ''
  let totalPlan = 0,
    totalDept = 0,
    totalRcvd = 0

  const fragment = document.createDocumentFragment()

  data.forEach((row) => {
    const plan = parseFloat(row.plan)
    const dept = parseFloat(row.departure)
    const rcvd = parseFloat(row.received)
    const transit = parseFloat(row.in_transit_qty)

    totalPlan += plan
    totalDept += dept
    totalRcvd += rcvd

    const transitIndicator =
      transit > 0
        ? `<span class="text-xs bg-warning text-black px-1 rounded animate-pulse">GAP: ${fmtNum.format(
            transit
          )}</span>`
        : ''

    let bgClass = 'bg-slate-800/50 border-slate-700'
    let accentColor = '#94a3b8'
    if (row.sequence === 'AGING') {
      bgClass = 'bg-red-900/20 border-red-800'
      accentColor = '#ef4444'
    } else if (row.sequence === 'SESSION 1') {
      bgClass = 'bg-blue-900/20 border-blue-800'
      accentColor = '#3b82f6'
    }

    const div = document.createElement('div')
    div.className = `flex-1 flex items-center justify-between px-4 py-1 rounded-lg border-l-4 ${bgClass} mb-2 shadow-lg last:mb-0`
    div.innerHTML = `
            <div class="flex flex-col w-[20%]">
                <span class="text-tv-xs font-mono text-slate-400 leading-tight">${
                  row.pst_display
                }</span>
                <span class="text-tv-sm font-bold tracking-wider" style="color: ${accentColor}">${
      row.sequence
    }</span>
            </div>
            <div class="flex flex-col w-[20%] items-end border-r border-slate-700 pr-4">
                <span class="delivery-label">PLAN</span>
                <span class="text-tv-lg font-mono font-bold text-white">${fmtNum.format(
                  plan
                )}</span>
            </div>
            <div class="flex flex-col w-[20%] items-end border-r border-slate-700 pr-4">
                <span class="delivery-label">DEPART</span>
                <span class="text-tv-lg font-mono font-bold text-info">${fmtNum.format(dept)}</span>
            </div>
             <div class="flex flex-col w-[25%] items-end">
                <div class="flex justify-between w-full">
                    <span class="delivery-label text-success">RECEIVED</span>
                    ${transitIndicator}
                </div>
                <span class="text-tv-lg font-mono font-bold text-success">${fmtNum.format(
                  rcvd
                )}</span>
            </div>
        `
    fragment.appendChild(div)
  })
  container.appendChild(fragment)

  if (totalPlan > 0) {
    const totalRate = (totalDept / totalPlan) * 100
    const fulfillRate = totalDept > 0 ? (totalRcvd / totalDept) * 100 : 0

    const ovrRateEl = document.getElementById('overall-rate')
    const ovrBgEl = document.getElementById('overall-bar-bg')
    const ovrLabelEl = document.getElementById('overall-label')

    if (ovrRateEl) {
      ovrRateEl.innerText = totalRate.toFixed(1) + '%'
      ovrRateEl.className = `text-tv-2xl font-mono font-bold leading-none ${
        totalRate >= 98 ? 'text-success' : totalRate >= 80 ? 'text-info' : 'text-danger'
      }`
      ovrLabelEl.innerHTML = `DEPARTURE RATE <span class="text-slate-500 mx-2">|</span> RECEIVE RATE: <span class="${
        fulfillRate >= 98 ? 'text-success' : 'text-warning'
      }">${fulfillRate.toFixed(1)}%</span>`
    }

    if (ovrBgEl) {
      ovrBgEl.className = `h-full w-full opacity-30 absolute bottom-0 transition-all duration-1000 ${
        totalRate >= 98 ? 'bg-success' : totalRate >= 80 ? 'bg-info' : 'bg-danger'
      }`
      ovrBgEl.style.height = Math.min(totalRate, 100) + '%'
    }
  }
}

// --- Right Panel Logic (Carousel System) ---

function cycleRightPanel() {
  const container = document.getElementById('right-panel-content')

  container.classList.remove('fade-enter-active')
  container.classList.add('fade-exit-active')

  setTimeout(() => {
    if (currentRightPanelMode === 'LOCATOR') {
      if (globalTransitData && globalTransitData.length > 0) {
        currentRightPanelMode = 'TRANSIT'
        currentTransitPage = 0
        renderTransitPage()
        resetCarouselTimer(SLIDE_DURATION_TRANSIT)
      } else {
        renderLocatorPage()
        resetCarouselTimer(SLIDE_DURATION_LOCATOR)
      }
    } else {
      currentRightPanelMode = 'LOCATOR'
      renderLocatorPage()
      resetCarouselTimer(SLIDE_DURATION_LOCATOR)
    }

    container.classList.remove('fade-exit-active')
    container.classList.add('fade-enter-active')
  }, 500)
}

function resetCarouselTimer(duration) {
  if (carouselTimer) clearTimeout(carouselTimer)
  carouselTimer = setTimeout(cycleRightPanel, duration)
}

function renderLocatorPage() {
  document.getElementById(
    'right-panel-title'
  ).innerHTML = `<span class="w-3 h-6 bg-warning rounded-sm"></span> WIP LOCATOR (FG)`
  document.getElementById('right-panel-title').className =
    'text-tv-lg font-bold text-warning flex items-center gap-3'

  const thead = document.getElementById('right-table-head')
  thead.innerHTML = `
        <tr>
            <th class="tv-table-header w-[10%]">LOC</th>
            <th class="tv-table-header w-[15%]">PST</th>
            <th class="tv-table-header w-[35%]">MODEL / PN</th>
            <th class="tv-table-header w-[10%]">LOT</th>
            <th class="tv-table-header text-right w-[15%]">QTY</th>
            <th class="tv-table-header text-right w-[15%]">REMAIN</th>
        </tr>
    `

  const tbody = document.getElementById('right-table-body')

  if (!globalLocatorData || globalLocatorData.length === 0) {
    renderEmptyRows(tbody, 'NO WIP DATA AVAILABLE')
    return
  }

  const totalPages = Math.ceil(globalLocatorData.length / ITEMS_PER_PAGE)
  if (currentLocatorPage >= totalPages) currentLocatorPage = 0
  document.getElementById('page-indicator').innerText = `LOC ${
    currentLocatorPage + 1
  }/${totalPages}`

  const start = currentLocatorPage * ITEMS_PER_PAGE
  const pageData = globalLocatorData.slice(start, start + ITEMS_PER_PAGE)
  const emptyRows = ITEMS_PER_PAGE - pageData.length

  tbody.innerHTML = ''
  pageData.forEach((row, idx) => {
    const remain = parseFloat(row.remain_del)
    const isCritical = remain < 0
    const rowClass = idx % 2 === 0 ? 'bg-[#1e293b]' : 'bg-[#161f32]'
    const locColor = isCritical ? 'text-white' : 'text-info'
    const criticalClass = isCritical ? 'bg-red-900/30 blink-critical border-l-4 border-danger' : ''

    tbody.innerHTML += `
            <tr class="tv-table-row ${rowClass} ${criticalClass}">
                <td class="tv-cell pl-4 font-bold ${locColor} text-tv-lg">${row.locator}</td>
                <td class="tv-cell text-slate-400 text-tv-sm">${row.pst}</td>
                <td class="tv-cell">
                    <div class="text-slate-200 text-tv-base leading-none truncate max-w-[300px]">${
                      row.model
                    }</div>
                    <div class="pn-text">${row.pn}</div>
                </td>
                <td class="tv-cell text-tv-sm text-slate-300">${row.lot}</td>
                <td class="tv-cell text-right font-bold text-tv-lg">${fmtNum.format(row.qty)}</td>
                <td class="tv-cell text-right font-bold text-tv-lg ${
                  isCritical ? 'text-red-400' : 'text-emerald-400'
                }">${fmtNum.format(remain)}</td>
            </tr>
        `
  })
  appendEmptyRows(tbody, emptyRows, pageData.length)
  currentLocatorPage++
}

function renderTransitPage() {
  document.getElementById(
    'right-panel-title'
  ).innerHTML = `<span class="w-3 h-6 bg-blue-500 rounded-sm"></span> IN-TRANSIT MONITORING (SJ)`
  document.getElementById('right-panel-title').className =
    'text-tv-lg font-bold text-blue-500 flex items-center gap-3'

  const thead = document.getElementById('right-table-head')
  thead.innerHTML = `
        <tr>
            <th class="tv-table-header w-[25%]">NO SJ</th>
            <th class="tv-table-header w-[20%]">DEPART TIME</th>
            <th class="tv-table-header w-[15%] text-center">MODELS</th>
            <th class="tv-table-header w-[20%] text-right">TOTAL QTY</th>
            <th class="tv-table-header w-[20%] text-center">STATUS</th>
        </tr>
    `

  const tbody = document.getElementById('right-table-body')

  if (!globalTransitData || globalTransitData.length === 0) {
    renderEmptyRows(tbody, 'NO IN-TRANSIT SHIPMENTS')
    return
  }

  const totalPages = Math.ceil(globalTransitData.length / ITEMS_PER_PAGE)
  if (currentTransitPage >= totalPages) currentTransitPage = 0
  document.getElementById('page-indicator').innerText = `TRNS ${
    currentTransitPage + 1
  }/${totalPages}`

  const start = currentTransitPage * ITEMS_PER_PAGE
  const pageData = globalTransitData.slice(start, start + ITEMS_PER_PAGE)
  const emptyRows = ITEMS_PER_PAGE - pageData.length

  tbody.innerHTML = ''
  pageData.forEach((row, idx) => {
    const rowClass = idx % 2 === 0 ? 'bg-[#1e293b]' : 'bg-[#161f32]'

    tbody.innerHTML += `
            <tr class="tv-table-row ${rowClass} border-l-4 border-blue-500/30">
                <td class="tv-cell pl-4 font-bold text-white text-tv-lg">${row.no_sj}</td>
                <td class="tv-cell text-slate-400 text-tv-sm">${row.depart_time}</td>
                <td class="tv-cell text-center text-tv-base text-slate-300">${
                  row.total_model
                } Items</td>
                <td class="tv-cell text-right font-bold text-info text-tv-lg">${fmtNum.format(
                  row.total_qty
                )}</td>
                <td class="tv-cell text-center">
                    <span class="bg-blue-900/50 text-blue-300 px-2 py-1 rounded text-sm border border-blue-800 animate-pulse">ON THE WAY</span>
                </td>
            </tr>
        `
  })
  appendEmptyRows(tbody, emptyRows, pageData.length)
  currentTransitPage++
}

function renderEmptyRows(tbody, msg) {
  tbody.innerHTML = ''
  for (let i = 0; i < ITEMS_PER_PAGE; i++) {
    const content =
      i === Math.floor(ITEMS_PER_PAGE / 2) ? `<span class="text-slate-500">${msg}</span>` : '&nbsp;'
    tbody.innerHTML += `<tr class="tv-table-row bg-[#1e293b]"><td colspan="6" class="text-center tv-cell">${content}</td></tr>`
  }
}

function appendEmptyRows(tbody, count, startIndex) {
  for (let i = 0; i < count; i++) {
    const rowClass = (startIndex + i) % 2 === 0 ? 'bg-[#1e293b]' : 'bg-[#161f32]'
    tbody.innerHTML += `<tr class="tv-table-row ${rowClass}"><td colspan="6" class="tv-cell">&nbsp;</td></tr>`
  }
}

document.addEventListener('DOMContentLoaded', () => {
  setInterval(updateTime, 1000)
  updateTime()
  fetchData()
  setInterval(fetchData, REFRESH_DATA_INTERVAL)
  resetCarouselTimer(SLIDE_DURATION_LOCATOR)
})
