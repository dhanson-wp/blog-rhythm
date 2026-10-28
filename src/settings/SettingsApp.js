/**
 * The Blog Rhythm settings page: seven cards in the spec order, a sticky
 * live preview, and save.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Page } from '@wordpress/admin-ui';
import { Badge, Button, Notice, Spinner, Stack, Text } from '@wordpress/ui';
import {
	clearHistory,
	exportData,
	getRhythm,
	getSettings,
	saveSettings,
} from '../shared/api';
import Pace from './cards/Pace';
import WhatCounts from './cards/WhatCounts';
import InYourWords from './cards/InYourWords';
import QuietStretches from './cards/QuietStretches';
import Reminders from './cards/Reminders';
import WidgetLook from './cards/WidgetLook';
import Privacy from './cards/Privacy';
import Preview from './Preview';
import {
	PACE_TARGETS,
	exportDate,
	previewData,
	targetError,
	toPayload,
} from './form';
import './style.scss';

// Enough history for every look, including twelve months of blocks.
const PREVIEW_WEEKS = 53;

function errorMessage( error, fallback ) {
	return error && typeof error.message === 'string' && error.message
		? error.message
		: fallback;
}

function download( data, filename ) {
	const blob = new Blob( [ JSON.stringify( data, null, 2 ) ], {
		type: 'application/json',
	} );
	const url = window.URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	link.hidden = true;
	document.body.appendChild( link );
	link.click();
	link.remove();
	window.URL.revokeObjectURL( url );
}

export default function SettingsApp( { timezone = '', email = '' } ) {
	const [ form, setForm ] = useState( null );
	const [ rhythm, setRhythm ] = useState( null );
	const [ loadFailed, setLoadFailed ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ exporting, setExporting ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const targetRef = useRef();
	const noticeRef = useRef();

	useEffect( () => {
		let live = true;
		Promise.allSettled( [
			getSettings(),
			getRhythm( PREVIEW_WEEKS ),
		] ).then( ( [ settings, payload ] ) => {
			if ( ! live ) {
				return;
			}
			if ( settings.status === 'fulfilled' && settings.value ) {
				setForm( settings.value );
			} else {
				setLoadFailed( true );
			}
			// Without the rhythm the preview still draws, just with no days.
			setRhythm( payload.status === 'fulfilled' ? payload.value : {} );
		} );
		return () => {
			live = false;
		};
	}, [] );

	useEffect( () => {
		if ( notice && noticeRef.current && noticeRef.current.scrollIntoView ) {
			noticeRef.current.scrollIntoView( { block: 'nearest' } );
		}
	}, [ notice ] );

	const update = ( patch ) =>
		setForm( ( current ) => ( { ...current, ...patch } ) );

	const setPace = ( pace ) =>
		setForm( ( current ) => ( {
			...current,
			pace,
			target:
				PACE_TARGETS[ pace ] !== undefined
					? PACE_TARGETS[ pace ]
					: current.target,
		} ) );

	const refreshRhythm = async () => {
		try {
			setRhythm( await getRhythm( PREVIEW_WEEKS ) );
		} catch {
			// The preview keeps what it had.
		}
	};

	const save = async () => {
		if ( saving || ! form ) {
			return;
		}
		if ( targetError( form.pace, form.target ) ) {
			if ( targetRef.current ) {
				targetRef.current.focus();
			}
			return;
		}
		setSaving( true );
		setNotice( null );
		try {
			const stored = await saveSettings( toPayload( form ) );
			if ( stored && typeof stored === 'object' ) {
				setForm( stored );
			}
			setNotice( {
				intent: 'success',
				text: __( 'Settings saved.', 'blog-rhythm' ),
			} );
			refreshRhythm();
		} catch ( error ) {
			setNotice( {
				intent: 'error',
				text: errorMessage(
					error,
					__(
						"Your changes couldn't be saved. Try again in a moment.",
						'blog-rhythm'
					)
				),
			} );
		}
		setSaving( false );
	};

	const onExport = async () => {
		setExporting( true );
		try {
			const data = await exportData();
			download(
				data,
				`blog-rhythm-export-${ exportDate( rhythm ) }.json`
			);
		} catch ( error ) {
			setNotice( {
				intent: 'error',
				text: errorMessage(
					error,
					__(
						"Your data couldn't be exported. Try again in a moment.",
						'blog-rhythm'
					)
				),
			} );
		}
		setExporting( false );
	};

	const onClear = async () => {
		try {
			await clearHistory();
		} catch ( error ) {
			return {
				error: errorMessage(
					error,
					__(
						"Your history couldn't be cleared. Try again in a moment.",
						'blog-rhythm'
					)
				),
			};
		}
		await refreshRhythm();
		setNotice( {
			intent: 'success',
			text: __( 'Your history is cleared.', 'blog-rhythm' ),
		} );
	};

	const saveButton = (
		<Button onClick={ save } loading={ saving } disabled={ ! form }>
			{ __( 'Save changes', 'blog-rhythm' ) }
		</Button>
	);

	let content;
	if ( loadFailed ) {
		content = (
			<Notice.Root intent="error">
				<Notice.Description>
					{ __(
						"Blog Rhythm couldn't load your settings. Try again in a moment.",
						'blog-rhythm'
					) }
				</Notice.Description>
			</Notice.Root>
		);
	} else if ( ! form ) {
		content = (
			<Stack
				align="center"
				justify="center"
				className="blog-rhythm-loading"
			>
				<Spinner />
			</Stack>
		);
	} else {
		content = (
			<div className="blog-rhythm-settings-body">
				<div className="blog-rhythm-settings-form">
					<div
						ref={ noticeRef }
						aria-live="polite"
						className="blog-rhythm-notice"
					>
						{ notice && (
							<Notice.Root intent={ notice.intent }>
								<Notice.Description>
									{ notice.text }
								</Notice.Description>
							</Notice.Root>
						) }
					</div>
					<Pace
						form={ form }
						onPace={ setPace }
						onTarget={ ( target ) => update( { target } ) }
						targetRef={ targetRef }
					/>
					<WhatCounts
						counts={ form.counts || {} }
						onChange={ ( counts ) => update( { counts } ) }
					/>
					<InYourWords
						intention={ form.intention || '' }
						onChange={ ( intention ) => update( { intention } ) }
					/>
					<QuietStretches
						resting={ !! form.resting }
						restUntil={ form.rest_until || '' }
						onResting={ ( resting ) => update( { resting } ) }
						onRestUntil={ ( value ) =>
							update( { rest_until: value } )
						}
					/>
					<Reminders
						reminders={ form.reminders || {} }
						timezone={ timezone }
						email={ email }
						onChange={ ( reminders ) => update( { reminders } ) }
					/>
					<WidgetLook
						look={ form.look }
						showSummary={ form.show_summary }
						showLogButton={ form.show_log_button }
						onChange={ update }
					/>
					<Privacy
						onExport={ onExport }
						onClear={ onClear }
						exporting={ exporting }
					/>
					<Stack
						direction="row"
						gap="md"
						align="center"
						wrap="wrap"
						className="blog-rhythm-foot"
					>
						{ saveButton }
						<Text className="blog-rhythm-help">
							{ __(
								'Changes show in the preview as you make them.',
								'blog-rhythm'
							) }
						</Text>
					</Stack>
				</div>
				<Preview data={ rhythm ? previewData( form, rhythm ) : null } />
			</div>
		);
	}

	return (
		<Page
			className="blog-rhythm-settings"
			title={ __( 'Blog Rhythm', 'blog-rhythm' ) }
			badges={
				<Badge intent="none">
					{ __( 'Only you can see this', 'blog-rhythm' ) }
				</Badge>
			}
			subTitle={ __(
				'Set the pace that suits you. Drafting and outlining count as showing up.',
				'blog-rhythm'
			) }
			actions={ form ? saveButton : null }
			showSidebarToggle={ false }
		>
			{ content }
		</Page>
	);
}
