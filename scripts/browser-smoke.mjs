import { chromium } from '@playwright/test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
const password=process.env.CLINICDESK_BROWSER_PASSWORD;
if(!password)throw new Error('Set CLINICDESK_BROWSER_PASSWORD to the local demo password.');
const browser=await chromium.launch({headless:true,executablePath:process.env.CLINICDESK_BROWSER_PATH??'C:/Program Files/Google/Chrome/Application/chrome.exe'});
const context=await browser.newContext({viewport:{width:1440,height:1000}});
const page=await context.newPage();
const base=process.env.CLINICDESK_URL??'http://127.0.0.1:8000';
const errors=[];
page.on('pageerror',e=>errors.push(e.message));
fs.mkdirSync('docs/screenshots',{recursive:true});
async function shot(name){await page.screenshot({path:path.join('docs/screenshots',name+'.png'),fullPage:true});}
async function signIn(role){await page.goto(base+'/login');await page.getByLabel('Email address').fill(role+'@clinicdesk.example');await page.getByLabel('Password',{exact:true}).fill(password);await page.getByRole('button',{name:'Sign in',exact:true}).click();await page.waitForURL('**/dashboard');}
async function signOut(){await page.getByRole('button',{name:'Sign out',exact:true}).click();await page.waitForURL(base+'/');}
page.on('dialog',dialog=>dialog.accept());
try{
 await page.goto(base);await shot('01-home-desktop');await page.getByRole('link',{name:'Explore our doctors'}).click();await page.getByRole('heading',{name:'Find your doctor.'}).waitFor();await shot('02-doctors-desktop');
 await signIn('patient');await shot('03-patient-dashboard');
 await page.getByRole('link',{name:'Book an appointment +'}).click();await page.locator('#doctor_id').selectOption({index:1});
 const date=new Date();date.setDate(date.getDate()+1);const day=new Intl.DateTimeFormat('sv-SE',{timeZone:'Asia/Karachi'}).format(date);
 await page.locator('#booking_date').fill(day);
 await page.waitForFunction(()=>document.querySelector('#starts_at').options.length>2);
 await shot('04-booking-availability');await page.locator('#starts_at').selectOption({index:1});await page.getByRole('button',{name:'Confirm appointment',exact:true}).click();await page.waitForURL(/\/appointments\/\d+$/);await page.getByText('Appointment booked.',{exact:true}).waitFor();await shot('05-appointment-booked');
 await page.getByRole('link',{name:'Reschedule',exact:true}).click();await page.waitForFunction(()=>document.querySelector('#starts_at').options.length>2);await page.locator('#starts_at').selectOption({index:1});await page.getByRole('button',{name:'Confirm reschedule',exact:true}).click();await page.getByText('Appointment rescheduled.',{exact:true}).waitFor();await page.getByRole('button',{name:'Cancel appointment',exact:true}).click();await page.getByText('Appointment cancelled.',{exact:true}).waitFor();await shot('06-appointment-cancelled');await signOut();
 await signIn('doctor');await shot('07-doctor-dashboard');await page.getByRole('link',{name:'View all appointments'}).click();await page.getByRole('heading',{name:'Appointments',exact:true}).waitFor();await signOut();
 await signIn('admin');await shot('08-admin-dashboard');await page.goto(base+'/admin/services/create');const name='Browser demo service '+Date.now();await page.getByLabel('Service name').fill(name);await page.getByLabel('Description').fill('Fictional browser verification service.');await page.getByRole('button',{name:'Save service',exact:true}).click();await page.getByText('Saved successfully.',{exact:true}).waitFor();await page.getByRole('row').filter({hasText:name}).getByRole('link',{name:'Edit',exact:true}).click();await page.getByLabel('Availability').selectOption('0');await page.getByRole('button',{name:'Save service',exact:true}).click();await page.getByRole('row').filter({hasText:name}).getByText('Inactive',{exact:true}).waitFor();await shot('09-admin-services');
 await page.goto(base+'/admin/schedules');await shot('10-admin-schedules');await signOut();
 await page.setViewportSize({width:390,height:844});await page.goto(base);await shot('11-home-mobile');await page.goto(base+'/doctors');await shot('12-doctors-mobile');
 assert.deepEqual(errors,[],'Browser JavaScript errors');console.log('PASS: desktop/mobile pages, patient booking/reschedule/cancel, doctor dashboard, admin service create/edit/deactivate. 12 screenshots.');
}finally{await browser.close();}

