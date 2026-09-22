<?php

use App\Models\ReportScore;
use App\Models\Role;
use App\Models\User;
use App\Policies\ReportScorePolicy;

function makePolicyUser(
    string $roleCode,
    ?int $studentId = null,
    bool $hasPermission = true
): User {
    $user = new class extends User
    {
        public bool $allowPermission = true;

        public function hasPermission(string $permissionCode): bool
        {
            return $this->allowPermission;
        }
    };

    $role = new Role();

    $role->forceFill([
        'code' => $roleCode,
        'name' => $roleCode,
    ]);

    $user->forceFill([
        'student_id' => $studentId,
    ]);

    $user->allowPermission = $hasPermission;

    $user->setRelation('role', $role);

    return $user;
}

function makeReportScore(
    string $status,
    int $studentId = 1
): ReportScore {
    $reportScore = new ReportScore();

    $reportScore->forceFill([
        'student_id' => $studentId,
        'status' => $status,
    ]);

    return $reportScore;
}

test('student cannot update own draft report score', function () {
    $user = makePolicyUser('STUDENT', 1);
    $reportScore = makeReportScore('DRAFT', 1);

    $policy = new ReportScorePolicy();

    expect(
        $policy->update($user, $reportScore)
    )->toBeFalse();
});

test('admin can update draft report score', function () {
    $user = makePolicyUser('ADMIN');
    $reportScore = makeReportScore('DRAFT');

    $policy = new ReportScorePolicy();

    expect(
        $policy->update($user, $reportScore)
    )->toBeTrue();
});

test('admin cannot update finalized report score', function () {
    $user = makePolicyUser('ADMIN');
    $reportScore = makeReportScore('FINALIZED');

    $policy = new ReportScorePolicy();

    expect(
        $policy->update($user, $reportScore)
    )->toBeFalse();
});

test('admin can correct finalized report score', function () {
    $user = makePolicyUser('ADMIN');
    $reportScore = makeReportScore('FINALIZED');

    $policy = new ReportScorePolicy();

    expect(
        $policy->correct($user, $reportScore)
    )->toBeTrue();
});

test('operator cannot correct finalized report score', function () {
    $user = makePolicyUser('OPERATOR');
    $reportScore = makeReportScore('FINALIZED');

    $policy = new ReportScorePolicy();

    expect(
        $policy->correct($user, $reportScore)
    )->toBeFalse();
});

test('admin can finalize locked report score', function () {
    $user = makePolicyUser('ADMIN');
    $reportScore = makeReportScore('LOCKED');

    $policy = new ReportScorePolicy();

    expect(
        $policy->finalize($user, $reportScore)
    )->toBeTrue();
});

test('admin cannot finalize draft report score', function () {
    $user = makePolicyUser('ADMIN');
    $reportScore = makeReportScore('DRAFT');

    $policy = new ReportScorePolicy();

    expect(
        $policy->finalize($user, $reportScore)
    )->toBeFalse();
});

test('operator cannot finalize locked report score', function () {
    $user = makePolicyUser('OPERATOR');
    $reportScore = makeReportScore('LOCKED');

    $policy = new ReportScorePolicy();

    expect(
        $policy->finalize($user, $reportScore)
    )->toBeFalse();
});

test('admin can lock draft report score', function () {
    $user = makePolicyUser('ADMIN');
    $reportScore = makeReportScore('DRAFT');

    $policy = new ReportScorePolicy();

    expect(
        $policy->lock($user, $reportScore)
    )->toBeTrue();
});

test('admin cannot lock already locked report score', function () {
    $user = makePolicyUser('ADMIN');
    $reportScore = makeReportScore('LOCKED');

    $policy = new ReportScorePolicy();

    expect(
        $policy->lock($user, $reportScore)
    )->toBeFalse();
});

test('admin cannot update draft report score without permission', function () {
    $user = makePolicyUser(
        roleCode: 'ADMIN',
        hasPermission: false
    );

    $reportScore = makeReportScore('DRAFT');

    $policy = new ReportScorePolicy();

    expect(
        $policy->update($user, $reportScore)
    )->toBeFalse();
});

test('admin cannot correct finalized report score without permission', function () {
    $user = makePolicyUser(
        roleCode: 'ADMIN',
        hasPermission: false
    );

    $reportScore = makeReportScore('FINALIZED');

    $policy = new ReportScorePolicy();

    expect(
        $policy->correct($user, $reportScore)
    )->toBeFalse();
});

test('admin cannot lock draft report score without permission', function () {
    $user = makePolicyUser(
        roleCode: 'ADMIN',
        hasPermission: false
    );

    $reportScore = makeReportScore('DRAFT');

    $policy = new ReportScorePolicy();

    expect(
        $policy->lock($user, $reportScore)
    )->toBeFalse();
});

test('admin cannot finalize locked report score without permission', function () {
    $user = makePolicyUser(
        roleCode: 'ADMIN',
        hasPermission: false
    );

    $reportScore = makeReportScore('LOCKED');

    $policy = new ReportScorePolicy();

    expect(
        $policy->finalize($user, $reportScore)
    )->toBeFalse();
});