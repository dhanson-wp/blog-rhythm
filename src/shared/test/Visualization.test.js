import { render, screen } from '@testing-library/react';
import Visualization from '../Visualization';

const settings = {
	pace: 'daily',
	target: 3,
	look: 'auto',
	counts: { publish: true, draft: true, idea: true, edit: false },
};
const summary = {
	count: 3,
	target: 3,
	pace: 'daily',
	period: 'week',
	resting: false,
	rest_until: null,
};

describe( 'Visualization', () => {
	test( 'renders a text alternative entry for every active day', () => {
		const days = {
			'2026-10-27': 'published',
			'2026-10-25': 'drafted',
			'2026-10-20': 'idea',
		};
		render(
			<Visualization
				look="auto"
				days={ days }
				today="2026-10-27"
				settings={ settings }
				summary={ summary }
			/>
		);
		const items = screen.getAllByRole( 'listitem' );
		expect( items.map( ( li ) => li.textContent ) ).toEqual( [
			'Oct 27: published',
			'Oct 25: drafted',
			'Oct 20: idea or outline',
		] );
	} );

	test( 'empty days renders without throwing and without list items', () => {
		render(
			<Visualization
				look="auto"
				days={ {} }
				today="2026-10-27"
				settings={ settings }
				summary={ { ...summary, count: 0 } }
			/>
		);
		expect( screen.queryAllByRole( 'listitem' ) ).toHaveLength( 0 );
	} );

	test( 'chooses the visual from look and pace', () => {
		const { container, rerender } = render(
			<Visualization look="auto" days={ {} } today="2026-10-27" settings={ settings } summary={ summary } />
		);
		expect( container.querySelector( '.blog-rhythm-dots' ) ).not.toBeNull();
		rerender(
			<Visualization look="auto" days={ {} } today="2026-10-27" settings={ { ...settings, pace: 'weekly' } } summary={ summary } />
		);
		expect( container.querySelector( '.blog-rhythm-weeks' ) ).not.toBeNull();
		rerender(
			<Visualization look="auto" days={ {} } today="2026-10-27" settings={ { ...settings, pace: 'monthly' } } summary={ summary } />
		);
		expect( container.querySelector( '.blog-rhythm-months' ) ).not.toBeNull();
		rerender(
			<Visualization look="dots" days={ {} } today="2026-10-27" settings={ { ...settings, pace: 'monthly' } } summary={ summary } />
		);
		expect( container.querySelector( '.blog-rhythm-dots' ) ).not.toBeNull();
	} );

	test( 'hatches only today while resting', () => {
		const { container } = render(
			<Visualization
				look="dots"
				days={ { '2026-10-20': 'drafted' } }
				today="2026-10-27"
				settings={ settings }
				summary={ { ...summary, resting: true } }
			/>
		);
		expect( container.querySelectorAll( '.blog-rhythm-cell.is-resting' ) ).toHaveLength( 1 );
		expect( container.querySelector( '.is-resting' ).classList.contains( 'is-today' ) ).toBe( true );
	} );
} );
