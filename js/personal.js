function translate(text) {
	const l10n = window.OC && window.OC.L10N
	if (l10n && typeof l10n.translate === 'function') {
		return l10n.translate('naversynchronization', text)
	}
	return text
}

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
		root.querySelectorAll('button.match').forEach((button) => {
			button.disabled = busy
		})
	}

	function formatTime(stamp) {
		if (!stamp) {
			return translate('Not synced yet')
		}
		return translate('Last sync') + ' ' + new Date(stamp * 1000).toLocaleString()
	}

	function render(next) {
		idInput.value = next.naverId || ''
		passwordInput.value = ''
		passwordInput.placeholder = next.passwordSet ? translate('Saved. Enter a new one only to change it') : ''
		passwordHint.textContent = next.passwordSet
			? translate('A password is saved. The field is empty because the password is not shown again. Enter a new one only when you want to change it.')
			: translate('If you use two-step verification, enter an application password.')
		calendarList.replaceChildren()
		const calendars = next.calendars || []
		if (calendars.length === 0) {
			const empty = document.createElement('li')
			empty.className = 'empty'
			empty.textContent = translate('No calendars have been imported yet.')
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
			name.textContent = calendar.name || translate('Naver calendar')
			main.appendChild(name)
			const badge = document.createElement('span')
			badge.className = 'badge'
			badge.textContent = translate('Read only')
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
			const actions = document.createElement('div')
			actions.className = 'meta-actions'
			if (calendar.nameCustomized) {
				actions.appendChild(matchButton(translate('Match Naver name'), () => matchNaver(calendar.id, true, false)))
			}
			if (calendar.colorCustomized) {
				actions.appendChild(matchButton(translate('Match Naver color'), () => matchNaver(calendar.id, false, true)))
			}
			if (actions.childNodes.length > 0) {
				item.appendChild(actions)
			}
			calendarList.appendChild(item)
		})
	}

	function matchButton(label, onClick) {
		const button = document.createElement('button')
		button.type = 'button'
		button.className = 'match'
		button.textContent = label
		button.addEventListener('click', (event) => {
			event.preventDefault()
			onClick()
		})
		return button
	}

	async function matchNaver(id, name, color) {
		await call('calendars/' + id + '/match-naver', 'POST', { name, color })
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
				throw new Error(payload.error || translate('Request failed.'))
			}
			if (payload.calendars) {
				render(payload)
			}
			if (payload.message) {
				setStatus(payload.message, false)
			}
			return payload
		} catch (error) {
			setStatus(error.message || translate('Request failed.'), true)
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
