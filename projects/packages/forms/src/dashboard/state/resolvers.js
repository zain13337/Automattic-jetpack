import apiFetch from '@wordpress/api-fetch';
import {
	dispatchAsync,
	fetchResponses,
	receiveResponsesFetch,
	failResponsesFetch,
} from './actions';

/**
 * Fetches responses from backend (API)
 *
 * @param {string} search - Some search term
 * @param {string }status - The response status (spam|trash)
 * @param {number} limit  - Maximum results to get from backend
 * @param {number} offset - The offset for the results (paging)
 * @returns {Promise}     - The fetch promise
 */
const fetchResponsesFromApi = ( search, status, limit = 20, offset = 0 ) => {
	const queryString = new URLSearchParams( {
		search,
		limit,
		offset,
		status,
	} ).toString();

	return apiFetch( { path: `/wpcom/v2/forms/responses?${ queryString }` } );
};

/**
 * getResponses resolver will trigger a backend request
 *
 * @param {string} search - Some search term
 * @param {string} status - The response status (spam|trash)
 * @param {number} limit  - Maximum results to get from backend
 * @param {number} offset - The offset for the results (paging)
 * @yields
 */
function* getResponses( search, status, limit = 20, offset = 0 ) {
	try {
		yield fetchResponses();
		const response = yield dispatchAsync( fetchResponsesFromApi, [
			search,
			status,
			limit,
			offset,
		] );
		yield receiveResponsesFetch( response );
	} catch ( error ) {
		yield failResponsesFetch( error );
	}
}

export default {
	getResponses,
};