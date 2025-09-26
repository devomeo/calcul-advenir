(function () {
'use strict';

if ( typeof window.aewData === 'undefined' ) {
return;
}

const data = window.aewData;
const restUrl = data.restUrl || '';
const nonce = data.nonce || '';
const i18n = data.i18n || {};
const baseUrl = restUrl.endsWith( '/' ) ? restUrl : restUrl + '/';

class AdvenirWizard {
constructor( container ) {
this.container = container;
this.progressEl = container.querySelector( '.aew-progress' );
this.stepEl = container.querySelector( '[data-aew-step-container]' );
this.prevButton = container.querySelector( '[data-aew-prev]' );
this.nextButton = container.querySelector( '[data-aew-next]' );
this.resultEl = container.querySelector( '[data-aew-result]' );

this.questions = [];
this.meta = {};
this.resultMessages = {};
this.currentIndex = 0;
this.answers = {};

this.handlePrev = this.handlePrev.bind( this );
this.handleNext = this.handleNext.bind( this );

this.prevButton.addEventListener( 'click', this.handlePrev );
this.nextButton.addEventListener( 'click', this.handleNext );
}

async init() {
this.setLoadingState( true );

try {
const response = await fetch( baseUrl + 'config', { credentials: 'same-origin' } );

if ( ! response.ok ) {
throw new Error( 'config_error' );
}

const data = await response.json();
this.questions = Array.isArray( data.questions ) ? data.questions : [];
this.meta = data.meta || {};
this.resultMessages = data.results || {};

if ( this.questions.length === 0 ) {
this.showErrorState( i18n.empty || 'Aucune question disponible.' );
return;
}

this.currentIndex = 0;
this.renderStep();
this.updateProgress();
this.setLoadingState( false );
} catch ( error ) {
this.showErrorState( i18n.loadError || 'Impossible de charger le questionnaire.' );
}
}

setLoadingState( isLoading ) {
if ( isLoading ) {
this.stepEl.innerHTML = '';
const loading = document.createElement( 'p' );
loading.className = 'aew-loading';
loading.textContent = i18n.loading || 'Chargement…';
this.stepEl.appendChild( loading );
this.prevButton.disabled = true;
this.nextButton.disabled = true;
} else {
this.prevButton.disabled = this.currentIndex === 0;
this.nextButton.disabled = false;
}
}

showErrorState( message ) {
this.stepEl.innerHTML = '';
const wrapper = document.createElement( 'div' );
wrapper.className = 'aew-error-card';

const title = document.createElement( 'h3' );
title.className = 'aew-error-title';
title.textContent = i18n.errorTitle || 'Une erreur est survenue';

const text = document.createElement( 'p' );
text.textContent = message;

const retry = document.createElement( 'button' );
retry.type = 'button';
retry.className = 'aew-btn aew-btn-primary';
retry.textContent = i18n.retry || 'Réessayer';
retry.addEventListener( 'click', () => this.init() );

wrapper.appendChild( title );
wrapper.appendChild( text );
wrapper.appendChild( retry );
this.stepEl.appendChild( wrapper );
this.prevButton.disabled = true;
this.nextButton.disabled = true;
}

renderStep() {
this.stepEl.innerHTML = '';
this.resultEl.hidden = true;
this.resultEl.innerHTML = '';

const question = this.questions[ this.currentIndex ];
if ( ! question ) {
return;
}

const container = document.createElement( 'div' );
container.className = 'aew-question';

const title = document.createElement( 'h2' );
title.className = 'aew-question-title';
title.textContent = question.label || '';
container.appendChild( title );

if ( question.description ) {
const description = document.createElement( 'p' );
description.className = 'aew-question-description';
description.textContent = String( question.description );
container.appendChild( description );
}

const field = question.type === 'number' ? this.renderNumberField( question ) : this.renderSingleChoiceField( question );
container.appendChild( field );

const error = document.createElement( 'div' );
error.className = 'aew-field-error';
error.id = 'aew-error-' + question.id;
error.setAttribute( 'role', 'alert' );
error.setAttribute( 'aria-live', 'polite' );
container.appendChild( error );

this.stepEl.appendChild( container );
this.updateButtons();
}

renderSingleChoiceField( question ) {
const fieldset = document.createElement( 'fieldset' );
fieldset.className = 'aew-options';
fieldset.setAttribute( 'data-question', question.id );

const legend = document.createElement( 'legend' );
legend.className = 'aew-visually-hidden';
legend.textContent = question.label || '';
fieldset.appendChild( legend );

const options = Array.isArray( question.options ) ? question.options : [];
const savedValue = this.answers[ question.id ];

options.forEach( ( option, index ) => {
const optionId = 'aew-' + question.id + '-' + index;

const wrapper = document.createElement( 'div' );
wrapper.className = 'aew-option';

const input = document.createElement( 'input' );
input.type = 'radio';
input.name = question.id;
input.id = optionId;
input.value = option.value;
input.required = !! question.required;

if ( savedValue && savedValue === option.value ) {
input.checked = true;
}

const label = document.createElement( 'label' );
label.htmlFor = optionId;
label.textContent = option.label || option.value;

wrapper.appendChild( input );
wrapper.appendChild( label );
fieldset.appendChild( wrapper );
} );

return fieldset;
}

renderNumberField( question ) {
const wrapper = document.createElement( 'div' );
wrapper.className = 'aew-number-field';

const input = document.createElement( 'input' );
input.type = 'number';
input.name = question.id;
input.id = 'aew-' + question.id;
input.required = !! question.required;

if ( question.attributes ) {
const attrs = question.attributes;
if ( typeof attrs.min !== 'undefined' ) {
input.min = attrs.min;
}
if ( typeof attrs.max !== 'undefined' ) {
input.max = attrs.max;
}
if ( typeof attrs.step !== 'undefined' ) {
input.step = attrs.step;
}
if ( typeof attrs.placeholder !== 'undefined' ) {
input.placeholder = attrs.placeholder;
}
}

if ( typeof this.answers[ question.id ] !== 'undefined' ) {
input.value = this.answers[ question.id ];
}

const label = document.createElement( 'label' );
label.className = 'aew-visually-hidden';
label.htmlFor = input.id;
label.textContent = question.label || '';

wrapper.appendChild( label );
wrapper.appendChild( input );

return wrapper;
}

handlePrev() {
if ( this.currentIndex === 0 ) {
return;
}

this.currentIndex -= 1;
this.renderStep();
this.updateProgress();
}

async handleNext() {
if ( ! this.validateCurrentStep() ) {
return;
}

if ( this.currentIndex === this.questions.length - 1 ) {
await this.evaluate();
return;
}

this.currentIndex += 1;
this.renderStep();
this.updateProgress();
}

validateCurrentStep() {
const question = this.questions[ this.currentIndex ];
if ( ! question ) {
return false;
}

const error = this.stepEl.querySelector( '.aew-field-error' );
if ( error ) {
error.textContent = '';
}

if ( question.type === 'number' ) {
const input = this.stepEl.querySelector( 'input[name="' + question.id + '"]' );
if ( ! input ) {
return false;
}

const value = input.value.trim();
if ( value === '' ) {
return this.setFieldError( error, i18n.required || 'Cette information est obligatoire.' );
}

const numberValue = Number( value );
if ( Number.isNaN( numberValue ) ) {
return this.setFieldError( error, i18n.invalidNumber || 'Merci d’entrer un nombre valide.' );
}

if ( input.min !== '' && ! Number.isNaN( Number( input.min ) ) && numberValue < Number( input.min ) ) {
const msg = i18n.minValue ? i18n.minValue.replace( '%s', input.min ) : 'Valeur trop faible.';
return this.setFieldError( error, msg );
}

if ( input.max !== '' && ! Number.isNaN( Number( input.max ) ) && numberValue > Number( input.max ) ) {
const msg = i18n.maxValue ? i18n.maxValue.replace( '%s', input.max ) : 'Valeur trop élevée.';
return this.setFieldError( error, msg );
}

this.answers[ question.id ] = numberValue;
return true;
}

const checked = this.stepEl.querySelector( 'input[name="' + question.id + '"]:checked' );
if ( ! checked ) {
return this.setFieldError( error, i18n.required || 'Cette information est obligatoire.' );
}

this.answers[ question.id ] = checked.value;
return true;
}

setFieldError( errorEl, message ) {
if ( errorEl ) {
errorEl.textContent = message;
}

return false;
}

updateProgress() {
if ( ! this.progressEl ) {
return;
}

const stepLabel = i18n.step || 'Étape';
const ofLabel = i18n.of || 'sur';
this.progressEl.textContent = stepLabel + ' ' + ( this.currentIndex + 1 ) + ' ' + ofLabel + ' ' + this.questions.length;
}

updateButtons() {
this.prevButton.disabled = this.currentIndex === 0;
this.nextButton.textContent = this.currentIndex === this.questions.length - 1 ? ( i18n.submit || 'Calculer mon éligibilité' ) : ( i18n.next || 'Suivant' );
}

async evaluate() {
this.nextButton.disabled = true;
this.prevButton.disabled = true;
this.resultEl.hidden = false;
this.resultEl.innerHTML = '';

const loader = document.createElement( 'p' );
loader.className = 'aew-loading';
loader.textContent = i18n.loading || 'Chargement…';
this.resultEl.appendChild( loader );

try {
const response = await fetch( baseUrl + 'evaluate', {
method: 'POST',
credentials: 'same-origin',
headers: {
'Content-Type': 'application/json',
'X-WP-Nonce': nonce,
},
body: JSON.stringify( { answers: this.answers } ),
} );

const payload = await response.json();

if ( ! response.ok ) {
throw new Error( payload.message || 'evaluate_error' );
}

this.renderResult( payload );
} catch ( error ) {
this.resultEl.innerHTML = '';
const errorBlock = document.createElement( 'div' );
errorBlock.className = 'aew-error-card';

const title = document.createElement( 'h3' );
title.className = 'aew-error-title';
title.textContent = i18n.errorTitle || 'Une erreur est survenue';

const text = document.createElement( 'p' );
text.textContent = typeof error.message === 'string' ? error.message : ( i18n.loadError || 'Une erreur est survenue.' );

errorBlock.appendChild( title );
errorBlock.appendChild( text );
this.resultEl.appendChild( errorBlock );
} finally {
this.nextButton.disabled = false;
this.prevButton.disabled = false;
}
}

renderResult( payload ) {
this.resultEl.innerHTML = '';

const wrapper = document.createElement( 'div' );
wrapper.className = 'aew-result-card';

const heading = document.createElement( 'h3' );
const headingClass = payload.eligible ? 'aew-result-title aew-result-title--success' : 'aew-result-title aew-result-title--warning';
const fallbackTitle = payload.eligible ? ( i18n.successTitle || 'Félicitations !' ) : ( i18n.infoTitle || 'Information' );
heading.textContent = ( payload.messages && payload.messages.title ) || fallbackTitle;
heading.className = headingClass;
wrapper.appendChild( heading );

if ( payload.messages && payload.messages.message ) {
const lead = document.createElement( 'p' );
lead.className = 'aew-result-message';
lead.textContent = payload.messages.message;
wrapper.appendChild( lead );
}

if ( payload.eligible && payload.amounts ) {
const amountsList = document.createElement( 'dl' );
amountsList.className = 'aew-amounts';

const currency = payload.amounts.currency || this.meta.currency || 'EUR';
const perPoint = this.formatCurrency( payload.amounts.amount_per_point || 0, currency );
const total = this.formatCurrency( payload.amounts.total || 0, currency );

amountsList.appendChild( this.createDefinition( i18n.perPoint || 'Montant par point', perPoint ) );
amountsList.appendChild( this.createDefinition( i18n.pointsEligible || 'Points éligibles', String( payload.amounts.points_eligible || 0 ) ) );
if ( typeof payload.amounts.points_requested !== 'undefined' ) {
amountsList.appendChild( this.createDefinition( i18n.pointsRequested || 'Points demandés', String( payload.amounts.points_requested ) ) );
}
amountsList.appendChild( this.createDefinition( i18n.totalEstimated || 'Total estimé', total ) );

wrapper.appendChild( amountsList );

if ( payload.scenario ) {
const scenarioBlock = document.createElement( 'div' );
scenarioBlock.className = 'aew-scenario';

if ( payload.scenario.label ) {
const scenarioTitle = document.createElement( 'p' );
scenarioTitle.className = 'aew-scenario-title';
scenarioTitle.textContent = payload.scenario.label;
scenarioBlock.appendChild( scenarioTitle );
}

if ( payload.scenario.success_message ) {
const scenarioMessage = document.createElement( 'p' );
scenarioMessage.textContent = payload.scenario.success_message;
scenarioBlock.appendChild( scenarioMessage );
}

if ( payload.scenario.footnote ) {
const footnote = document.createElement( 'p' );
footnote.className = 'aew-footnote';
footnote.textContent = payload.scenario.footnote;
scenarioBlock.appendChild( footnote );
}

wrapper.appendChild( scenarioBlock );
}
}

const summaryTitle = document.createElement( 'h4' );
summaryTitle.className = 'aew-summary-title';
summaryTitle.textContent = i18n.answersTitle || 'Vos réponses';
wrapper.appendChild( summaryTitle );

const list = document.createElement( 'ul' );
list.className = 'aew-summary';

this.questions.forEach( ( question ) => {
const answer = this.answers[ question.id ];
if ( typeof answer === 'undefined' ) {
return;
}

const item = document.createElement( 'li' );
const label = document.createElement( 'span' );
label.className = 'aew-summary-label';
label.textContent = question.label || question.id;

const valueSpan = document.createElement( 'span' );
valueSpan.className = 'aew-summary-value';

if ( question.type === 'number' ) {
valueSpan.textContent = String( answer );
} else {
const optionLabel = this.findOptionLabel( question, answer );
valueSpan.textContent = optionLabel || answer;
}

item.appendChild( label );
item.appendChild( valueSpan );
list.appendChild( item );
} );

wrapper.appendChild( list );
this.resultEl.appendChild( wrapper );
}

createDefinition( label, value ) {
const fragment = document.createDocumentFragment();
const term = document.createElement( 'dt' );
term.textContent = label;
const definition = document.createElement( 'dd' );
definition.textContent = value;
fragment.appendChild( term );
fragment.appendChild( definition );
return fragment;
}

findOptionLabel( question, value ) {
const options = Array.isArray( question.options ) ? question.options : [];
const match = options.find( ( option ) => option.value === value );
return match ? match.label : '';
}

formatCurrency( amount, currency ) {
try {
return new Intl.NumberFormat( 'fr-FR', {
style: 'currency',
currency: currency || 'EUR',
maximumFractionDigits: 0,
} ).format( amount );
} catch ( error ) {
return amount + ' ' + ( currency || 'EUR' );
}
}
}

document.addEventListener( 'DOMContentLoaded', () => {
const containers = document.querySelectorAll( '[data-aew-wizard]' );

containers.forEach( ( container ) => {
const wizard = new AdvenirWizard( container );
wizard.init();
} );
} );
})();
