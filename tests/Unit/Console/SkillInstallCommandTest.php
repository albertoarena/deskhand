<?php

declare(strict_types=1);

use Deskhand\Console\Command\SkillInstallCommand;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->dir = deskhandTempDir();
});

afterEach(function () {
    deskhandRemoveDir($this->dir);
});

it('installs the skill into the project .claude/skills', function () {
    $original = getcwd();
    chdir($this->dir);

    try {
        $tester = new CommandTester(new SkillInstallCommand);
        $exit = $tester->execute([]);
    } finally {
        chdir($original ?: $this->dir);
    }

    $target = $this->dir.'/.claude/skills/deskhand/SKILL.md';

    expect($exit)->toBe(0)
        ->and(is_file($target))->toBeTrue()
        ->and(file_get_contents($target))->toContain('name: deskhand')
        ->and($tester->getDisplay())->toContain('Installed the deskhand skill');
});

it('reports an update when the skill already exists', function () {
    $original = getcwd();
    chdir($this->dir);

    try {
        (new CommandTester(new SkillInstallCommand))->execute([]);
        $tester = new CommandTester(new SkillInstallCommand);
        $tester->execute([]);
    } finally {
        chdir($original ?: $this->dir);
    }

    expect($tester->getDisplay())->toContain('Updated the deskhand skill');
});

it('installs globally with --global', function () {
    $home = $this->dir;
    $originalHome = getenv('HOME');
    putenv("HOME={$home}");

    try {
        (new CommandTester(new SkillInstallCommand))->execute(['--global' => true]);
    } finally {
        putenv($originalHome === false ? 'HOME' : "HOME={$originalHome}");
    }

    expect(is_file($home.'/.claude/skills/deskhand/SKILL.md'))->toBeTrue();
});
