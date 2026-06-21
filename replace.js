const fs = require('fs');

let code = fs.readFileSync('src/App.tsx', 'utf-8');

const replacements = [
  ['bg-[#0A0A0A]', 'bg-white dark:bg-[#0A0A0A]'],
  ['text-neutral-300', 'text-neutral-800 dark:text-neutral-300'],
  ['border-neutral-800', 'border-neutral-200 dark:border-neutral-800'],
  ['border-neutral-800/80', 'border-neutral-200 dark:border-neutral-800/80'],
  ['border-neutral-800/60', 'border-neutral-200 dark:border-neutral-800/60'],
  ['bg-[#111111]', 'bg-gray-50 dark:bg-[#111111]'],
  ['text-white', 'text-black dark:text-white'],
  ['text-neutral-400', 'text-neutral-600 dark:text-neutral-400'],
  ['text-neutral-500', 'text-neutral-500 dark:text-neutral-500'], // Wait, 500 is fine in both usually, maybe gray-500
  ['bg-neutral-900', 'bg-gray-100 dark:bg-neutral-900'],
  ['bg-[#0a0a0a]', 'bg-white dark:bg-[#0a0a0a]'],
  ['bg-neutral-800', 'bg-gray-200 dark:bg-neutral-800'],
  ['bg-[#0f0f0f]', 'bg-white dark:bg-[#0f0f0f]'],
  ['hover:bg-neutral-900', 'hover:bg-gray-100 dark:hover:bg-neutral-900'],
  ['hover:bg-neutral-700', 'hover:bg-gray-200 dark:hover:bg-neutral-700'],
  ['hover:bg-neutral-800', 'hover:bg-gray-300 dark:hover:bg-neutral-800'],
  ['hover:border-neutral-700', 'hover:border-neutral-300 dark:hover:border-neutral-700'],
  ['bg-white text-black', 'bg-black text-white dark:bg-white dark:text-black'], // Buttons
  ['hover:bg-neutral-200', 'hover:bg-neutral-800 dark:hover:bg-neutral-200'], // Button hover
  ['text-neutral-600', 'text-neutral-500 dark:text-neutral-600'],
  ['text-neutral-200', 'text-neutral-800 dark:text-neutral-200'],
];

replacements.forEach(([from, to]) => {
  code = code.split(from).join(to);
});

fs.writeFileSync('src/App.tsx', code);
console.log("Replaced classes");
