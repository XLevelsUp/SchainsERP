import { describe, expect, it } from 'vitest'
import { defineComponent, ref } from 'vue'
import { mount } from '@vue/test-utils'
import BaseInput from '@/components/ui/BaseInput.vue'
import { isValidPhone, PHONE_ERROR, phoneDigits } from '@/lib/phone'

// Users' phone numbers (agreed 2026-11-23): exactly 10 digits, a leading 0
// allowed. The backend refuses anything else and any number another user
// already has; the forms check the length first and only let digits in.

describe('phone numbers', () => {
  it('accepts exactly 10 digits, a leading 0 included', () => {
    expect(isValidPhone('9876543210')).toBe(true)
    expect(isValidPhone('0123456789')).toBe(true)
    expect(isValidPhone(' 9876543210 ')).toBe(true)
    for (const bad of ['', '987654321', '98765432100', '98765 4321', '+919876543', 'abcdefghij']) {
      expect(isValidPhone(bad), bad).toBe(false)
    }
    expect(PHONE_ERROR).toBe('Phone number must be exactly 10 digits.')
  })

  it('keeps only digits, at most 10', () => {
    expect(phoneDigits('98765 43210')).toBe('9876543210')
    expect(phoneDigits('+91-98765-43210')).toBe('9198765432')
    expect(phoneDigits('0a1b2')).toBe('012')
    expect(phoneDigits('')).toBe('')
  })

  it('never shows a character the phone field drops', async () => {
    const Host = defineComponent({
      components: { BaseInput },
      setup: () => ({ phone: ref('98765'), phoneDigits }),
      template: '<BaseInput v-model="phone" :sanitize="phoneDigits" />',
    })
    const wrapper = mount(Host)
    const input = wrapper.find('input')

    await input.setValue('98765a')
    expect((input.element as HTMLInputElement).value).toBe('98765')
    expect((wrapper.vm as unknown as { phone: string }).phone).toBe('98765')

    await input.setValue('0987654321999')
    expect((input.element as HTMLInputElement).value).toBe('0987654321')
    expect((wrapper.vm as unknown as { phone: string }).phone).toBe('0987654321')
  })

  it('leaves fields without a filter as typed', async () => {
    const Host = defineComponent({
      components: { BaseInput },
      setup: () => ({ name: ref('') }),
      template: '<BaseInput v-model="name" />',
    })
    const wrapper = mount(Host)
    await wrapper.find('input').setValue('John Smith 2')
    expect((wrapper.vm as unknown as { name: string }).name).toBe('John Smith 2')
  })
})
