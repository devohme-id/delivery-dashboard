tailwind.config = {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        bgDark: '#0b1121',
        cardDark: '#1e293b',
        success: '#22c55e',
        danger: '#ef4444',
        warning: '#eab308',
        info: '#3b82f6',
      },
      fontFamily: {
        display: ['Oswald', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'monospace'],
      },
      fontSize: {
        'tv-xs': '1rem', // 16px
        'tv-sm': '1.25rem', // 20px
        'tv-base': '1.5rem', // 24px
        'tv-lg': '1.875rem', // 30px
        'tv-xl': '2.25rem', // 36px
        'tv-2xl': '3rem', // 48px
        'tv-huge': '4.5rem', // 72px
      },
    },
  },
}
