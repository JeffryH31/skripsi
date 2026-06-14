<script setup>
const PRESET_COLORS = [
    '#3B82F6', '#6366F1', '#8B5CF6', '#EC4899',
    '#EF4444', '#F97316', '#EAB308', '#22C55E',
    '#14B8A6', '#06B6D4',
];

const props = defineProps({
    modelValue: { type: String, default: '#3B82F6' },
    label: { type: String, default: 'Color' },
});

const emit = defineEmits(['update:modelValue']);

const normalizeHex = (value) => {
    const raw = (value || '').trim();
    if (!raw) return props.modelValue;
    const hex = raw.startsWith('#') ? raw : `#${raw}`;
    return /^#[0-9A-Fa-f]{6}$/.test(hex) ? hex.toUpperCase() : props.modelValue;
};

const selectColor = (color) => emit('update:modelValue', color);
const onHexBlur = (e) => emit('update:modelValue', normalizeHex(e.target.value));
</script>

<template>
    <div>
        <div class="text-caption text-medium-emphasis mb-2">{{ label }}</div>
        <div class="color-swatches">
            <button
                v-for="color in PRESET_COLORS"
                :key="color"
                class="color-swatch"
                :class="{ 'color-swatch--active': modelValue === color }"
                :style="{ backgroundColor: color }"
                type="button"
                @click="selectColor(color)"
            />
            <label class="color-swatch color-swatch--custom" title="Custom color">
                <input
                    type="color"
                    :value="modelValue"
                    class="custom-color-input"
                    @input="selectColor($event.target.value)"
                />
                <v-icon size="16" color="grey">mdi-eyedropper</v-icon>
            </label>
        </div>
        <v-text-field
            :model-value="modelValue"
            label="Hex"
            variant="outlined"
            density="compact"
            hide-details
            class="mt-2"
            @blur="onHexBlur"
            @keyup.enter="onHexBlur"
        />
    </div>
</template>

<style scoped>
.color-swatches {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.color-swatch {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid transparent;
    cursor: pointer;
    transition: transform 0.15s, border-color 0.15s;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.color-swatch:hover {
    transform: scale(1.15);
}

.color-swatch--active {
    border-color: white;
    box-shadow: 0 0 0 2px currentColor;
}

.color-swatch--custom {
    background: conic-gradient(
        #EF4444, #F97316, #EAB308, #22C55E,
        #14B8A6, #06B6D4, #3B82F6, #6366F1,
        #8B5CF6, #EC4899, #EF4444
    );
    position: relative;
    overflow: hidden;
}

.custom-color-input {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}
</style>
