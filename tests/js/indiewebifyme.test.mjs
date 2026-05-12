import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { JSDOM } from 'jsdom';

const script = readFileSync('public/js/indiewebifyme.js', 'utf8');

// waits for the assertion to pass,
// or gives up at the specified timeout
// and throws the latest error
async function waitFor(assertion, timeout = 250) {
	const started = Date.now();
	let lastError;

	while (Date.now() - started < timeout) {
		try {
			assertion();
			return;
		} catch (error) {
			lastError = error;
			await new Promise((resolve) => setTimeout(resolve, 5));
		}
	}

	throw lastError;
}

test('checks rel-me results and updates the badge and progress UI', async () => {
	const dom = new JSDOM(
		`
	<a class="results-url" href="https://example.com/?a=1&b=2#me">site</a>
	<div class="progress-bar" aria-valuenow="0" style="width: 1%"></div>
	<ul>
		<li class="rel-me-result">
			<a href="https://profile.example/me?x=1&y=2#about">profile</a>
			<div class="spinner-border spinner-border-sm" role="status">
				<span class="visually-hidden">Loading...</span>
			</div>
			<span class="badge d-none">badge text</span>
		</li>
	</ul>
`,
		{
			url: 'https://indiewebify.me/test',
			runScripts: 'outside-only', // so we can manually call the script on this DOM
		},
	);

	const { window } = dom;
	let requestedUrl;

	// mock fetch used by the script
	window.fetch = async (url) => {
		requestedUrl = url;
		return {
			json: async () => ({
				pass: true,
				status: 200,
				response: 'Works perfectly',
			}),
		};
	};

	window.eval(script); // run indiewebify script
	window.document.dispatchEvent(new window.Event('DOMContentLoaded')); // activate indiewebify script

	await waitFor(() => {
		assert.equal(window.document.querySelector('.badge').textContent, 'Works perfectly');
	});

	assert.equal(window.document.querySelector('.spinner-border'), null);
	assert.equal(window.document.querySelector('.badge').classList.contains('text-bg-success'), true);
	assert.equal(window.document.querySelector('.badge').classList.contains('d-none'), false);
	assert.equal(window.document.querySelector('.progress-bar').getAttribute('aria-valuenow'), '100');

	assert.ok(requestedUrl);
	const parsed = new URL(requestedUrl, window.location.href);

	assert.equal(parsed.pathname, '/rel-me-check/');
	assert.equal(parsed.searchParams.get('url1'), 'https://example.com/?a=1&b=2#me');
	assert.equal(parsed.searchParams.get('url2'), 'https://profile.example/me?x=1&y=2#about');
});
