$(function () {
	navCall();
	owlSlider();
	timelineTab();
	updateTimeLineContentVisibility();
	modalTeamData();
	coreValueHover();
});

$(window).on('load', function () {
	abilityHeadingHeight();
});

$(window).on('resize', function () {
	abilityHeadingHeight();
	updateTimeLineContentVisibility();

	if ($(window).innerWidth() > 992) {
		removeNavSelector($('#navCall'));
	}
});

function navCall() {
	$('#navCall').on('click', function () {
		const $this = $(this);
		const $navSite = $('#navSite');
		const $nav = $navSite.find('.nav');
		const subNavSelector = '.sub-nav';

		$this.toggleClass('is-active');
		$navSite.toggleClass('d-none position-absolute start-0 top-100 w-100 bg-white');
		$nav.toggleClass('flex-column text-center');
		$(subNavSelector).removeClass('d-flex flex-column pt-2 pb-3 mt-2');
		$navSite.find('.has_nav .arrow i').addClass('bi-chevron-down').removeClass('bi-chevron-up');
	});

	$('.has_nav .arrow').on('click', function () {
		const $this = $(this);
		const $icon = $this.find('i');
		const $subNav = $this.next('.sub-nav');

		if ($this.closest('.has_nav').hasClass('clicked')) {
			$this.closest('.has_nav').removeClass('clicked');
			$icon.addClass('bi-chevron-down').removeClass('bi-chevron-up');
			$subNav.removeClass('d-flex flex-column pt-2 pb-3 mt-2');
		} else {
			$('.has_nav').removeClass('clicked');
			$('.has_nav .arrow i').addClass('bi-chevron-down').removeClass('bi-chevron-up');
			$('.sub-nav').removeClass('d-flex flex-column pt-2 pb-3 mt-2');
			$this.closest('.has_nav').addClass('clicked');
			$icon.addClass('bi-chevron-up').removeClass('bi-chevron-down');
			$subNav.addClass('d-flex flex-column pt-2 pb-3 mt-2');
		}
	});
}

function removeNavSelector(ele) {
	const $navSite = $('#navSite');
	const $nav = $navSite.find('.nav');
	const subNavSelector = '.sub-nav';

	ele.removeClass('is-active');
	$navSite.removeClass('d-none position-absolute start-0 top-100 w-100 bg-white').addClass('d-none');
	$nav.removeClass('flex-column text-center');
	$('.has_nav .arrow i').addClass('bi-chevron-down').removeClass('bi-chevron-up');
	$(subNavSelector).removeClass('d-flex flex-column pt-2 pb-3 mt-2');
}

function abilityHeadingHeight() {
	const abilities = $('.abilities');

	if (abilities.length === 0) return;

	const windowWidth = $(window).innerWidth();

	if (windowWidth > 767) {
		const abilityH2s = abilities.find('.box .copy h2');
		const maxHeight = Math.max(...abilityH2s.map((_, el) => $(el).height()).get());
		abilityH2s.css('min-height', maxHeight);
	} else {
		abilities.find('.box .copy h2').css('min-height', '');
	}
}

function owlSlider() {
	if ($('.partners__slider').length > 0) {
		$('.partners__slider').owlCarousel({
			loop: false,
			margin: 0,
			dots: false,
			nav: true,
			navText: ['<i class="bi bi-chevron-left"></i>', '<i class="bi bi-chevron-right"></i>'],
			responsive: {
				0: {
					items: 1,
				},
				600: {
					items: 2,
				},
				1000: {
					items: 3,
				},
			},
		});
	}
}

function coreValueHover() {
	$('#coreValues .values__list .box').on('mouseover', function () {
		const $this = $(this);
		const valueData = $(this).data('value');
		$('#coreValues .values__list .box').removeClass('over');
		$this.addClass('over');
		$('#coreValues .values__content .copy-value').hide();
		$('#coreValues .values__content .copy-value.' + valueData).show();
	});
}

function timelineTab() {
	const $timelineBars = $('#timeline .time-line__year-bar .bar');
	const $timelineContentCopies = $('#timeline .time-line__content .copy');

	$timelineBars.on('mouseover', function () {
		const year = $(this).data('year');

		$timelineBars.removeClass('active');
		$(this).addClass('active');

		$timelineContentCopies.addClass('d-none');
		$timelineContentCopies.filter('.' + year).removeClass('d-none');

		updateTimeLineContentVisibility();
	});
}

function updateTimeLineContentVisibility() {
	const $timelineContentCopies = $('#timeline .time-line__content .copy');

	if ($(window).innerWidth() < 768) {
		$timelineContentCopies.removeClass('d-none');
	} else {
		const $activeBar = $('#timeline .time-line__year-bar .bar.active');

		if ($activeBar.length) {
			const activeYear = $activeBar.data('year');
			$timelineContentCopies.addClass('d-none');
			$timelineContentCopies.filter('.' + activeYear).removeClass('d-none');
		}
	}
}

function modalTeamData() {
	$('.team .team__box .cta').on('click', function (e) {
		e.preventDefault();

		let $clickedElement = $(this),
			$clickedElementParent = $(this).closest('.team__box'),
			$thisColor = $clickedElement.css('background-color');

		$('#modalTeam').modal('show');

		let imgSrc = $clickedElementParent.find('.img img').attr('src'),
			name = $clickedElementParent.find('h2').text(),
			initialName = $clickedElementParent.find('h2').text().split(' ')[0],
			role = $clickedElementParent.find('h3').text(),
			copyMeta = $clickedElementParent.find('.content-flow').html();

		$('#imageModal').attr('src', imgSrc).removeClass('logomodal');
		$('#copyModal').html(copyMeta);
		$('#modalName').text(name);
		$('#initialName').text(initialName);
		$('#modalRole').text(role);
		$('#modalTeam .modal-content').css('border-top-color', $thisColor);
		$('#modalTeam .img').css('background-color', $thisColor);
	});
}
