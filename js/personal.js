function requestToken() {
	if (window.OC && window.OC.requestToken) {
		return window.OC.requestToken
	}
	const head = document.querySelector('head')
	return head ? head.getAttribute('data-requesttoken') || '' : ''
}

function appUrl(path) {
	const queryAt = path.indexOf('?')
	const query = queryAt === -1 ? '' : path.slice(queryAt)
	const route = '/apps/naversynchronization/' + (queryAt === -1 ? path : path.slice(0, queryAt))
	if (window.OC && typeof window.OC.generateUrl === 'function') {
		return window.OC.generateUrl(route) + query
	}
	return '/index.php' + route + query
}

function init() {
	const root = document.getElementById('naver-sync')
	if (!root) {
		return
	}
	const state = JSON.parse(root.dataset.state || '{}')
	const idInput = document.getElementById('naver-id')
	const passwordInput = document.getElementById('naver-password')
	const passwordHint = document.getElementById('naver-password-hint')
	const calendarList = document.getElementById('naver-calendars')
	const status = document.getElementById('naver-status')
	const buttons = [
		document.getElementById('naver-save'),
		document.getElementById('naver-save-selection'),
		document.getElementById('naver-sync-now'),
		document.getElementById('naver-refresh'),
	]

	function setStatus(message, isError) {
		status.textContent = message || ''
		status.classList.toggle('error', Boolean(isError))
		status.classList.toggle('ok', Boolean(message) && !isError)
	}

	function setBusy(busy) {
		buttons.forEach((button) => {
			button.disabled = busy
		})
	}

	function formatTime(stamp) {
		if (!stamp) {
			return '아직 동기화하지 않음'
		}
		return '마지막 동기화 ' + new Date(stamp * 1000).toLocaleString()
	}

	function render(next) {
		idInput.value = next.naverId || ''
		passwordInput.value = ''
		passwordInput.placeholder = next.passwordSet ? '저장되어 있습니다. 바꿀 때만 입력' : ''
		passwordHint.textContent = next.passwordSet
			? '암호가 저장되어 있습니다. 칸이 비어 있는 것은 암호를 다시 보여 주지 않기 때문이고, 바꿀 때만 새로 입력하면 됩니다.'
			: '2단계 인증이면 애플리케이션 비밀번호를 입력하세요.'
		calendarList.replaceChildren()
		const calendars = next.calendars || []
		if (calendars.length === 0) {
			const empty = document.createElement('li')
			empty.className = 'empty'
			empty.textContent = '아직 가져온 캘린더가 없습니다.'
			calendarList.appendChild(empty)
			return
		}
		calendars.forEach((calendar) => {
			const item = document.createElement('li')
			const label = document.createElement('label')
			label.className = 'row'
			const checkbox = document.createElement('input')
			checkbox.type = 'checkbox'
			checkbox.checked = Boolean(calendar.enabled)
			checkbox.dataset.id = String(calendar.id)
			label.appendChild(checkbox)
			const main = document.createElement('span')
			main.className = 'main'
			if (/^#[0-9A-Fa-f]{3,8}$/.test(calendar.color || '')) {
				const swatch = document.createElement('span')
				swatch.className = 'swatch'
				swatch.style.background = calendar.color
				swatch.setAttribute('aria-hidden', 'true')
				main.appendChild(swatch)
			}
			const name = document.createElement('span')
			name.className = 'name'
			name.textContent = calendar.name || '네이버 캘린더'
			main.appendChild(name)
			const badge = document.createElement('span')
			badge.className = 'badge'
			badge.textContent = '읽기 전용'
			main.appendChild(badge)
			label.appendChild(main)
			item.appendChild(label)
			const bits = [formatTime(calendar.lastSync)]
			if (calendar.lastSummary) {
				bits.push(calendar.lastSummary)
			}
			const meta = document.createElement('p')
			meta.className = 'meta'
			meta.textContent = bits.join(' · ')
			item.appendChild(meta)
			if (calendar.lastError) {
				const error = document.createElement('p')
				error.className = 'meta error'
				error.textContent = calendar.lastError
				item.appendChild(error)
			}
			calendarList.appendChild(item)
		})
	}

	function selection() {
		return Array.from(calendarList.querySelectorAll('input[type="checkbox"]')).map((checkbox) => ({
			id: Number(checkbox.dataset.id),
			enabled: checkbox.checked,
		}))
	}

	async function call(path, method, body) {
		setBusy(true)
		setStatus('', false)
		try {
			const response = await fetch(appUrl(path), {
				method,
				credentials: 'same-origin',
				headers: {
					requesttoken: requestToken(),
					'Content-Type': 'application/json',
					Accept: 'application/json',
				},
				body: body === undefined ? undefined : JSON.stringify(body),
			})
			const payload = await response.json()
			if (!response.ok) {
				throw new Error(payload.error || '요청에 실패했습니다.')
			}
			if (payload.calendars) {
				render(payload)
			}
			if (payload.message) {
				setStatus(payload.message, false)
			}
			return payload
		} catch (error) {
			setStatus(error.message || '요청에 실패했습니다.', true)
			return null
		} finally {
			setBusy(false)
		}
	}

	document.getElementById('naver-save').addEventListener('click', () => {
		call('account', 'PUT', {
			naverId: idInput.value,
			password: passwordInput.value,
		})
	})
	document.getElementById('naver-refresh').addEventListener('click', () => {
		call('calendars?refresh=1', 'GET')
	})
	document.getElementById('naver-save-selection').addEventListener('click', () => {
		call('calendars', 'PUT', { calendars: selection() })
	})
	document.getElementById('naver-sync-now').addEventListener('click', () => {
		call('sync', 'POST', {})
	})

	render(state)
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init)
} else {
	init()
}
