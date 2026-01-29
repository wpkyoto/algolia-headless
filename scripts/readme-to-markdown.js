#!/usr/bin/env node

/**
 * Convert readme.txt to README.md
 * Replaces grunt-wp-readme-to-markdown functionality
 */

const fs = require('fs');
const path = require('path');

const readmeTxtPath = path.join(__dirname, '../readme.txt');
const readmeMdPath = path.join(__dirname, '../README.md');

function convertReadme() {
  if (!fs.existsSync(readmeTxtPath)) {
    console.error('Error: readme.txt not found');
    process.exit(1);
  }

  let content = fs.readFileSync(readmeTxtPath, 'utf8');

  // Convert WP readme format to Markdown
  content = content
    // Headers
    .replace(/^=== (.+) ===$/gm, '# $1')
    .replace(/^== (.+) ==$/gm, '## $1')
    .replace(/^= (.+) =$/gm, '### $1')
    // Bold
    .replace(/\*\*(.+?)\*\*/g, '**$1**')
    // Code blocks
    .replace(/`([^`]+)`/g, '`$1`')
    // Links (basic support)
    .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '[$1]($2)');

  // Add WordPress plugin badge information at the top
  const lines = content.split('\n');
  const pluginInfo = [];

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    if (line.startsWith('Donate link:') ||
        line.startsWith('Tags:') ||
        line.startsWith('Requires at least:') ||
        line.startsWith('Tested up to:') ||
        line.startsWith('Requires PHP:') ||
        line.startsWith('Stable tag:') ||
        line.startsWith('License:')) {
      pluginInfo.push(line);
    }
  }

  fs.writeFileSync(readmeMdPath, content, 'utf8');
  console.log('✓ Successfully converted readme.txt to README.md');
}

convertReadme();
