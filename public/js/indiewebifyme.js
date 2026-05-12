function ready(fn) {
	if (document.readyState != 'loading') {
		fn();
	} else {
		document.addEventListener('DOMContentLoaded', fn);
	}
}

ready(function () {
	async function checkRelMe(url1, url2) {
		const params = new URLSearchParams({
			url1: url1,
			url2: url2,
		});
		const response = await fetch(`/rel-me-check/?${params}`);
		return response.json();
	}

	const relMeResults = document.querySelectorAll('.rel-me-result');

	if (relMeResults.length > 0) {
		const results_url = document.querySelector('.results-url').href;
		const progressBar = document.querySelector('.progress-bar');
		const progressIncrement = Math.round((1 / relMeResults.length) * 100);
		let currentProgress = 0;

		for (let i = 0; i < relMeResults.length; i++) {
			const url = relMeResults[i].querySelector('a');
			const spinner = relMeResults[i].querySelector('.spinner-border');
			const badge = relMeResults[i].querySelector('.badge');

			const parsed = new URL(url.href);
			if (!['http:', 'https:'].includes(parsed.protocol)) {
				continue;
			}

			checkRelMe(results_url, url.href).then(function (json) {
				badge.textContent = json.response;
				if (json.status != 200) {
					badge.textContent = `${json.response} (HTTP ${json.status})`;
				}

				if (json.pass) {
					badge.classList.add('text-bg-success');
				} else {
					badge.classList.add('text-bg-warning');
				}

				spinner.remove();
				badge.classList.remove('d-none');

				currentProgress += progressIncrement;
				if (currentProgress > 100) {
					currentProgress = 100;
				}
				progressBar.setAttribute('aria-valuenow', currentProgress);
				progressBar.style.width = currentProgress + '%';
			});
		}
	}
});
