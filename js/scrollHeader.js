let prevScrollPos = window.scrollY;

window.addEventListener('scroll', () => {
	requestAnimationFrame(() => {
		const currentScrollPos = window.scrollY;
		const navbar = document.getElementById('header');

		if (prevScrollPos > currentScrollPos) {
			navbar.style.top = '0';
		} else {
			navbar.style.top = '-80px';
		}

		prevScrollPos = currentScrollPos;
	});
});
