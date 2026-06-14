import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';
import { createVuetify } from 'vuetify';
import { aliases, mdi } from 'vuetify/iconsets/mdi';

// Light theme configuration
const lightTheme = {
    dark: false,
    colors: {
        primary: '#7B68EE',      // ClickUp purple
        'primary-darken-1': '#6366F1',
        secondary: '#8B5CF6',    
        accent: '#49CCF9',       // ClickUp blue
        error: '#FF6B6B',        // Softer red
        warning: '#FFB84D',      // Amber
        info: '#49CCF9',         // Cyan
        success: '#6BC950',      // Green
        background: '#F9FAFB',   
        surface: '#FFFFFF',      
    },
};

// ClickUp-style Dark theme 
const darkTheme = {
    dark: true,
    colors: {
        primary: '#7B68EE',           // ClickUp signature purple
        'primary-darken-1': '#6366F1',
        secondary: '#8B5CF6',         
        accent: '#49CCF9',            // ClickUp accent blue
        error: '#FF6B6B',             // Softer error red
        warning: '#FFB84D',           // Warm amber
        info: '#49CCF9',              // Info blue
        success: '#6BC950',           // ClickUp green
        background: '#121212',        // True dark background
        surface: '#1e1e1e',           // Card/panel surface
        'surface-light': '#2d2d30',   // Lighter surface for hover
        'surface-variant': '#252526', // Variant surface
        'on-background': '#E4E4E7',   // Text on background
        'on-surface': '#E4E4E7',      // Text on surface
        'on-surface-variant': '#9CA3AF', // Muted text
    },
};

const vuetify = createVuetify({
    icons: {
        defaultSet: 'mdi',
        aliases,
        sets: { mdi },
    },
    theme: {
        defaultTheme: 'darkTheme',
        themes: {
            lightTheme,
            darkTheme,
        },
    },
    typography: {
        fontFamily: "'Plus Jakarta Sans', sans-serif",
    },
    defaults: {
        VBtn: {
            variant: 'flat',
            rounded: 'lg',
        },
        VTextField: {
            variant: 'outlined',
            density: 'comfortable',
            rounded: 'lg',
            color: 'primary',
        },
        VCard: {
            rounded: 'lg',
            elevation: 0,
        },
        VCheckbox: {
            color: 'primary',
        },
        VList: {
            bgColor: 'surface',
        },
        VListItem: {
            rounded: 'lg',
        },
        VChip: {
            size: 'small',
        },
        VTabs: {
            color: 'primary',
            sliderColor: 'primary',
        },
    },
});

export default vuetify;
