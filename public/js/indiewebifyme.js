function ready(fn) {
	if (document.readyState != "loading") {
		fn();
	} else {
		document.addEventListener("DOMContentLoaded", fn);
	}
}

ready(function () {
	async function checkRelMe(url1, url2) {
		const response = await fetch(`/rel-me-check?url1=${url1}&url2=${url2}`);
		return response.json();
	}

	let relMeResults = document.querySelectorAll(".rel-me-result");

	if (relMeResults.length > 0) {
		let results_url = document.querySelector(".results-url").href;
		let progressBar = document.querySelector(".progress-bar");
		let currentProgress = 0;
		let progressIncrement = Math.round((1 / relMeResults.length) * 100);

		for (let i = 0; i < relMeResults.length; i++) {
			let url = relMeResults[i].querySelector("a");
			let spinner = relMeResults[i].querySelector(".spinner-border");
			let badge = relMeResults[i].querySelector(".badge");

			checkRelMe(results_url, url.href).then(function (json) {
				// console.log(json);
				badge.textContent = json.response;
				if (json.status != 200) {
					badge.textContent = `${json.response} (HTTP ${json.status})`;
				}

				if (json.pass) {
					badge.classList.add("text-bg-success");
				} else {
					badge.classList.add("text-bg-warning");
				}

				spinner.remove(); // remove loading spinner
				badge.classList.remove("d-none"); // display result badge

				// extend the progress bar
				currentProgress += progressIncrement;
				if (currentProgress > 100) {
					currentProgress = 100;
				}
				// console.log('currentProgress', currentProgress);
				progressBar.setAttribute("aria-valuenow", currentProgress);
				progressBar.style.width = currentProgress + "%";
			});
		}
	}
});
