(() => {
    const toggle = document.getElementById('user-menu-toggle')
    const menu = document.getElementById('menu-right')

    if (!toggle || !menu) {
        return
    }

    const setOpen = (isOpen) => {
        menu.classList.toggle('is-open', isOpen)
        menu.setAttribute('aria-hidden', String(!isOpen))
        toggle.setAttribute('aria-expanded', String(isOpen))
    }

    toggle.addEventListener('click', () => {
        setOpen(!menu.classList.contains('is-open'))
    })

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false)
        }
    })
})()
